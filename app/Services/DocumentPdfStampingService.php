<?php

namespace App\Services;

use App\Enums\SignatureFieldType;
use App\Models\Document;
use App\Models\Signature;
use App\Models\SignatureField;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use setasign\Fpdi\Fpdi;
use Throwable;

class DocumentPdfStampingService
{
    private function secureDiskName(): string
    {
        return (string) config('filesystems.docutrust_disk', 'local');
    }

    public function generatePreparedPdf(Document $document): ?string
    {
        return $this->generate($document->fresh(), 'prepared');
    }

    public function generateFinalPdf(Document $document): ?string
    {
        return $this->generate($document->fresh(), 'final');
    }

    private function generate(Document $document, string $mode): ?string
    {
        $sourcePath = $mode === 'final'
            ? ($document->prepared_pdf_path ?: $document->sourcePdfPath())
            : $document->sourcePdfPath();

        if (! is_string($sourcePath) || $sourcePath === '') {
            return null;
        }

        $disk = Storage::disk($this->secureDiskName());
        if (! $disk->exists($sourcePath)) {
            return null;
        }

        try {
            $document->loadMissing([
                'signatureFields.signer',
                'signatures.signatureField',
                'documentSigners',
            ]);

            $pdf = new Fpdi();
            $pageCount = $pdf->setSourceFile($disk->path($sourcePath));

            /** @var array<int, \Illuminate\Support\Collection<int, SignatureField>> $fieldsByPage */
            $fieldsByPage = $document->signatureFields
                ->groupBy(fn (SignatureField $field): int => (int) ($field->page_number ?? 1))
                ->all();

            /** @var array<int, Signature> $signaturesByFieldId */
            $signaturesByFieldId = $document->signatures
                ->filter(fn (Signature $signature): bool => $signature->signature_field_id !== null)
                ->keyBy('signature_field_id')
                ->all();

            for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
                $templateId = $pdf->importPage($pageNumber);
                $size = $pdf->getTemplateSize($templateId);
                $orientation = $size['width'] > $size['height'] ? 'L' : 'P';

                $pdf->AddPage($orientation, [$size['width'], $size['height']]);
                $pdf->useTemplate($templateId);

                /** @var \Illuminate\Support\Collection<int, SignatureField> $pageFields */
                $pageFields = $fieldsByPage[$pageNumber] ?? collect();
                foreach ($pageFields as $field) {
                    $this->renderField($pdf, $field, $size['width'], $size['height'], $mode, $signaturesByFieldId[$field->id] ?? null);
                }
            }

            $generatedPath = sprintf(
                'documents/generated/%s-%s-%s.pdf',
                $document->id,
                $mode,
                Str::uuid()->toString()
            );

            $disk->put($generatedPath, $pdf->Output('S'));

            $attributes = $mode === 'final'
                ? ['final_pdf_path' => $generatedPath]
                : ['prepared_pdf_path' => $generatedPath, 'final_pdf_path' => null];

            $document->update($attributes);

            return $generatedPath;
        } catch (Throwable $throwable) {
            Log::warning('PDF stamping failed', [
                'document_id' => $document->id,
                'mode' => $mode,
                'message' => $throwable->getMessage(),
                'exception' => $throwable::class,
            ]);

            return null;
        }
    }

    private function renderField(Fpdi $pdf, SignatureField $field, float $pageWidth, float $pageHeight, string $mode, ?Signature $signature): void
    {
        $position = $field->position_data;
        if (! is_array($position)) {
            return;
        }

        $x = (float) (($position['x'] ?? 0) * $pageWidth);
        $y = (float) (($position['y'] ?? 0) * $pageHeight);
        $width = (float) (($position['width'] ?? 0) * $pageWidth);
        $height = (float) (($position['height'] ?? 0) * $pageHeight);

        if ($width <= 0 || $height <= 0) {
            return;
        }

        $type = $field->type instanceof SignatureFieldType ? $field->type : SignatureFieldType::from((string) $field->type);

        if ($mode === 'prepared') {
            $this->drawPreparedField($pdf, $type, $field, $x, $y, $width, $height);
            return;
        }

        $this->drawFinalField($pdf, $type, $field, $signature, $x, $y, $width, $height);
    }

    private function drawPreparedField(Fpdi $pdf, SignatureFieldType $type, SignatureField $field, float $x, float $y, float $width, float $height): void
    {
        [$r, $g, $b] = $this->colorForType($type);
        $label = $this->labelForType($type);
        $isToggle = in_array($type, [SignatureFieldType::Checkbox, SignatureFieldType::Radio], true);
        $pdf->SetDrawColor($r, $g, $b);
        $pdf->SetTextColor($r, $g, $b);
        $pdf->SetLineWidth($isToggle ? 0.3 : 0.35);
        $pdf->Rect($x, $y, $width, $height);

        $signerName = (string) ($field->signer?->name ?? '');

        if ($isToggle) {
            $boxSize = min($height - 2.4, $width * 0.26);
            $boxSize = max(3.4, $boxSize);
            $boxX = $x + 1.2;
            $boxY = $y + (($height - $boxSize) / 2);
            $pdf->Rect($boxX, $boxY, $boxSize, $boxSize);
            $pdf->SetFont('Helvetica', '', max(7, min(9, $height * 1.55)));
            $pdf->SetXY($boxX + $boxSize + 1.4, $y + max(1.1, ($height - 4.6) / 2));
            $pdf->Cell(max(4, $width - ($boxSize + 4)), 4.4, $label, 0, 0, 'L');
            return;
        }

        $pdf->SetFont('Helvetica', '', max(7, min(11, $height * 1.6)));
        $pdf->SetXY($x + 1.5, $y + 1.3);
        $pdf->Cell($width - 3, max(4, $height / 2), $label, 0, 0, 'L');

        if ($signerName !== '') {
            $pdf->Line($x + 1.5, $y + ($height - 2.4), $x + $width - 1.5, $y + ($height - 2.4));
            $pdf->SetFont('Helvetica', '', max(6, min(8, $height * 1.05)));
            $pdf->SetTextColor(100, 116, 139);
            $pdf->SetXY($x + 1.5, $y + max(3.8, $height * 0.5));
            $pdf->Cell($width - 3, max(3, $height / 3), $signerName, 0, 0, 'L');
        }
    }

    private function drawFinalField(Fpdi $pdf, SignatureFieldType $type, SignatureField $field, ?Signature $signature, float $x, float $y, float $width, float $height): void
    {
        [$r, $g, $b] = $this->colorForType($type);
        $pdf->SetDrawColor($r, $g, $b);
        $pdf->SetLineWidth(0.25);
        $pdf->Rect($x, $y, $width, $height);

        if (in_array($type, [SignatureFieldType::Signature, SignatureFieldType::SignatureLeft, SignatureFieldType::SignatureRight], true)) {
            $this->drawSignatureImage($pdf, $type, $signature, $x, $y, $width, $height);
            return;
        }

        $value = $this->resolvedFieldValue($type, $field, $signature);
        if ($value === '') {
            return;
        }

        $pdf->SetFont('Helvetica', '', max(8, min(14, $height * 1.9)));
        $pdf->SetTextColor(15, 23, 42);
        $pdf->SetXY($x + 1.5, $y + max(1.5, $height * 0.18));
        $pdf->MultiCell($width - 3, max(3.8, $height * 0.55), $value, 0, 'L');
    }

    private function drawSignatureImage(Fpdi $pdf, SignatureFieldType $type, ?Signature $signature, float $x, float $y, float $width, float $height): void
    {
        $path = $signature?->signature_path;
        if (! is_string($path) || $path === '') {
            return;
        }

        $disk = Storage::disk($this->secureDiskName());
        if (! $disk->exists($path)) {
            return;
        }

        $imagePath = $disk->path($path);
        $margin = 1.2;
        $renderWidth = max(4, $width - ($margin * 2));
        $renderHeight = max(4, $height - ($margin * 2));
        $renderX = $x + $margin;

        if ($type === SignatureFieldType::SignatureRight) {
            $renderX = $x + $width - $renderWidth - $margin;
        } elseif ($type === SignatureFieldType::Signature) {
            $renderX = $x + (($width - $renderWidth) / 2);
        }

        $pdf->Image($imagePath, $renderX, $y + $margin, $renderWidth, $renderHeight, 'PNG');
    }

    private function resolvedFieldValue(SignatureFieldType $type, SignatureField $field, ?Signature $signature = null): string
    {
        if (is_string($signature?->submitted_value) && $signature->submitted_value !== '') {
            return $signature->submitted_value;
        }

        $signer = $field->signer;
        if ($signer === null) {
            return '';
        }

        return match ($type) {
            SignatureFieldType::Name => (string) $signer->name,
            SignatureFieldType::Date => now()->format('M j, Y'),
            SignatureFieldType::Email => (string) $signer->email,
            SignatureFieldType::Initials => collect(explode(' ', (string) $signer->name))
                ->filter()
                ->take(2)
                ->map(fn (string $part): string => strtoupper(substr($part, 0, 1)))
                ->implode(''),
            SignatureFieldType::Checkbox => 'X',
            SignatureFieldType::Radio => 'O',
            default => $this->labelForType($type),
        };
    }

    /**
     * @return array{0:int,1:int,2:int}
     */
    private function colorForType(SignatureFieldType $type): array
    {
        return match ($type) {
            SignatureFieldType::Text => [202, 138, 4],
            SignatureFieldType::Name => [21, 128, 61],
            SignatureFieldType::Date => [109, 40, 217],
            SignatureFieldType::Email => [190, 24, 93],
            SignatureFieldType::Initials => [162, 28, 175],
            SignatureFieldType::Checkbox => [2, 132, 199],
            SignatureFieldType::Radio => [79, 70, 229],
            SignatureFieldType::SignatureLeft => [15, 118, 110],
            SignatureFieldType::SignatureRight => [3, 105, 161],
            default => [37, 99, 235],
        };
    }

    private function labelForType(SignatureFieldType $type): string
    {
        return match ($type) {
            SignatureFieldType::Signature => 'Signature',
            SignatureFieldType::SignatureLeft => 'Signature',
            SignatureFieldType::SignatureRight => 'Signature',
            SignatureFieldType::Text => 'Text field',
            SignatureFieldType::Checkbox => 'Checkbox',
            SignatureFieldType::Radio => 'Radio',
            SignatureFieldType::Name => 'Name',
            SignatureFieldType::Date => 'Date',
            SignatureFieldType::Email => 'Email',
            SignatureFieldType::Initials => 'Initials',
        };
    }
}
