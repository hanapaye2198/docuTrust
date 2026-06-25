<?php

namespace App\Services;

use App\Concerns\ResolvesSecureDisk;
use App\Contracts\SignerKeyStore;
use App\Data\FieldSignatureCaptureResult;
use App\Models\DocumentSigner;
use App\Models\Signature;
use App\Models\SignatureField;
use App\Trust\Authorization\TrustAuthorizationRequiredException;
use App\Trust\Authorization\TrustAuthorizationSessionService;
use GdImage;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class FieldSignatureCaptureService
{
    use ResolvesSecureDisk;

    private const SIGNATURE_IMAGE_WIDTH = 480;

    private const SIGNATURE_IMAGE_HEIGHT = 160;

    // NOTE: Signatures saved before this fix could be stored as JPEG (.jpg).
    // Existing Signature records with signature_path ending in .jpg should be
    // re-requested from the signer. Do NOT auto-delete old files.

    public function __construct(
        private readonly SignerKeyStore $signerKeyStore,
        private readonly PkiSignatureService $pkiSignatureService,
        private readonly SignerCertificateService $signerCertificateService,
        private readonly TrustAuthorizationSessionService $trustAuthorizationSessionService,
        private readonly SigningMethodService $signingMethodService,
    ) {}

    public function capture(
        DocumentSigner $signer,
        SignatureField $field,
        ?string $submittedValue,
        ?string $signatureImage,
        string $ipAddress,
    ): FieldSignatureCaptureResult {
        $document = $signer->document;

        if ($field->document_id !== $document->id || $field->signer_id !== $signer->id) {
            throw new RuntimeException('Signature field does not belong to the signer.');
        }

        $existingSignature = Signature::query()
            ->where('signature_field_id', $field->id)
            ->where('signer_id', $signer->id)
            ->first();

        if ($existingSignature !== null) {
            return new FieldSignatureCaptureResult(
                document: $document->fresh(),
                signer: $signer->fresh(),
                signature: $existingSignature->fresh(),
                message: __('Field already completed.'),
            );
        }

        $this->ensureActiveTrustAuthorizationIfRequired($signer);
        $this->ensureSignerKeyPair($signer);
        $signerCertificate = $this->signerCertificateService->getOrIssueForSigner($signer->fresh());
        $signerKeyPair = $this->signerKeyStore->keyPairFor($signer);

        $resolvedSubmittedValue = $this->resolveSubmittedFieldValue($field, $signer, $submittedValue);
        $signatureImagePath = $this->shouldPersistSignatureImage($field)
            ? $this->storeSubmittedSignatureImage($signatureImage)
            : null;

        $signature = Signature::query()->create([
            'signature_field_id' => $field->id,
            'document_id' => $document->id,
            'signer_id' => $signer->id,
            'signer_certificate_id' => $signerCertificate->id,
            'signature_path' => $signatureImagePath,
            'submitted_value' => $resolvedSubmittedValue,
            'signature_value' => null,
            'signature_hash' => null,
            'public_key_fingerprint' => $this->pkiSignatureService->fingerprint($signerKeyPair['public_key']),
            'signature_algorithm' => 'RSA-SHA256',
            'position_data' => null,
        ]);

        $this->deleteStoredSignatureIfReplaced($existingSignature, $signatureImagePath);
        SignatureAuditLogger::fieldSigned($document, $signer, $ipAddress);

        $fieldType = $field->type->value;
        $isSignatureField = in_array($fieldType, ['signature', 'signature_left', 'signature_right'], true);
        $message = $isSignatureField ? __('Signature saved.') : __('Field saved.');

        return new FieldSignatureCaptureResult(
            document: $document->fresh(),
            signer: $signer->fresh(),
            signature: $signature->fresh(),
            message: $message,
        );
    }

    private function ensureSignerKeyPair(DocumentSigner $signer): void
    {
        if ($this->signerKeyStore->hasKeyPair($signer)) {
            return;
        }

        $keys = $this->pkiSignatureService->generateKeyPair();
        $this->signerKeyStore->storeKeyPair($signer, $keys['public_key'], $keys['private_key']);
    }

    private function ensureActiveTrustAuthorizationIfRequired(DocumentSigner $signer): void
    {
        if (! $this->signingMethodService->requiresTrustAuthorization($signer)) {
            return;
        }

        $providerName = trim((string) config('services.remote_signing.provider_name', 'remote_managed'));
        $authorization = $this->trustAuthorizationSessionService->activeForSigner($signer, $providerName);

        if ($authorization !== null) {
            return;
        }

        throw new TrustAuthorizationRequiredException(
            __('Start trust authorization before completing your assigned fields.')
        );
    }

    private function shouldPersistSignatureImage(SignatureField $field): bool
    {
        return in_array($field->type->value, ['signature', 'signature_left', 'signature_right'], true);
    }

    private function resolveSubmittedFieldValue(SignatureField $field, DocumentSigner $signer, ?string $submittedValue): ?string
    {
        $value = is_string($submittedValue) ? trim($submittedValue) : null;
        if ($value !== null && $value !== '') {
            return $value;
        }

        return match ($field->type->value) {
            'name' => $signer->name,
            'date' => now()->format('M j, Y'),
            'email' => $signer->email,
            'initials' => collect(explode(' ', (string) $signer->name))
                ->filter()
                ->take(2)
                ->map(fn (string $part): string => strtoupper(substr($part, 0, 1)))
                ->implode(''),
            'checkbox' => 'X',
            'radio' => 'O',
            default => null,
        };
    }

    private function storeSubmittedSignatureImage(?string $dataUrl): ?string
    {
        if (! is_string($dataUrl) || $dataUrl === '') {
            return null;
        }

        if (! preg_match('/^data:image\/(?P<type>png|jpeg|jpg);base64,(?P<data>.+)$/', $dataUrl, $matches)) {
            return null;
        }

        $binary = base64_decode((string) $matches['data'], true);
        if ($binary === false) {
            return null;
        }

        $path = 'signatures/'.Str::uuid()->toString().'.png';
        Storage::disk($this->secureDiskName())->put($path, $this->normalizeSignatureImage($binary));

        return $path;
    }

    private function normalizeSignatureImage(string $binary): string
    {
        if (strlen($binary) < 8) {
            throw new InvalidArgumentException('Invalid signature image data.');
        }

        $isPng = substr($binary, 0, 4) === "\x89PNG";
        $isJpeg = substr($binary, 0, 2) === "\xFF\xD8";

        if (! $isPng && ! $isJpeg) {
            throw new InvalidArgumentException('Signature image must be PNG or JPEG.');
        }

        if (strlen($binary) > 2 * 1024 * 1024) {
            throw new InvalidArgumentException('Signature image must be under 2 MB.');
        }

        $src = $this->createImageFromBinary($binary, $isPng);
        $dst = $this->createSignatureCanvas($isPng);

        $srcWidth = imagesx($src);
        $srcHeight = imagesy($src);
        if ($srcWidth <= 0 || $srcHeight <= 0) {
            imagedestroy($src);
            imagedestroy($dst);

            throw new RuntimeException('GD decoded an invalid signature image size.');
        }

        $scale = min(
            self::SIGNATURE_IMAGE_WIDTH / $srcWidth,
            self::SIGNATURE_IMAGE_HEIGHT / $srcHeight,
            1.0,
        );
        $dstWidth = (int) round($srcWidth * $scale);
        $dstHeight = (int) round($srcHeight * $scale);
        $dstX = (int) round((self::SIGNATURE_IMAGE_WIDTH - $dstWidth) / 2);
        $dstY = (int) round((self::SIGNATURE_IMAGE_HEIGHT - $dstHeight) / 2);

        imagecopyresampled($dst, $src, $dstX, $dstY, 0, 0, $dstWidth, $dstHeight, $srcWidth, $srcHeight);
        imagedestroy($src);

        ob_start();
        imagepng($dst);
        $outputBinary = ob_get_clean();
        imagedestroy($dst);

        if (! is_string($outputBinary) || $outputBinary === '') {
            throw new RuntimeException('GD could not encode the signature PNG.');
        }

        return $outputBinary;
    }

    private function createImageFromBinary(string $binary, bool $isPng): GdImage
    {
        $src = $isPng
            ? @imagecreatefrompng('data://image/png;base64,'.base64_encode($binary))
            : @imagecreatefromjpeg('data://image/jpeg;base64,'.base64_encode($binary));

        if (! ($src instanceof GdImage)) {
            throw new RuntimeException('GD could not open the submitted signature image.');
        }

        return $src;
    }

    private function createSignatureCanvas(bool $transparent): GdImage
    {
        $dst = imagecreatetruecolor(self::SIGNATURE_IMAGE_WIDTH, self::SIGNATURE_IMAGE_HEIGHT);
        if (! ($dst instanceof GdImage)) {
            throw new RuntimeException('GD could not create the signature canvas.');
        }

        imagealphablending($dst, false);
        imagesavealpha($dst, true);

        if ($transparent) {
            $background = imagecolorallocatealpha($dst, 0, 0, 0, 127);
        } else {
            $background = imagecolorallocate($dst, 255, 255, 255);
        }

        if ($background === false) {
            imagedestroy($dst);

            throw new RuntimeException('GD could not allocate the signature background color.');
        }

        imagefilledrectangle($dst, 0, 0, self::SIGNATURE_IMAGE_WIDTH, self::SIGNATURE_IMAGE_HEIGHT, $background);
        imagealphablending($dst, true);

        return $dst;
    }

    private function deleteStoredSignatureIfReplaced(?Signature $existingSignature, ?string $newPath): void
    {
        $existingPath = $existingSignature?->signature_path;
        if (! is_string($existingPath) || $existingPath === '' || $existingPath === $newPath) {
            return;
        }

        $disk = Storage::disk($this->secureDiskName());
        if ($disk->exists($existingPath)) {
            $disk->delete($existingPath);
        }
    }
}
