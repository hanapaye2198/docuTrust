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
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class FieldSignatureCaptureService
{
    use ResolvesSecureDisk;

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

        if (! preg_match('/^data:image\/(?P<type>png|jpeg|jpg|webp);base64,(?P<data>.+)$/', $dataUrl, $matches)) {
            return null;
        }

        $binary = base64_decode((string) $matches['data'], true);
        if ($binary === false) {
            return null;
        }

        $extension = $matches['type'] === 'jpeg' ? 'jpg' : (string) $matches['type'];
        $path = 'signatures/'.Str::uuid()->toString().'.'.$extension;
        Storage::disk($this->secureDiskName())->put($path, $binary);

        return $path;
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
