<?php

namespace App\Services;

use App\Concerns\ResolvesSecureDisk;
use App\Models\NotarialRegisterEntry;
use App\Models\NotaryCredential;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use setasign\Fpdi\Fpdi;
use Throwable;

class NotarySealService
{
    use ResolvesSecureDisk;

    /**
     * Apply the notary seal and signature to a document PDF.
     */
    public function applyNotarySeal(
        string $sourcePdfPath,
        NotaryCredential $credential,
        NotarialRegisterEntry $entry,
    ): ?string {
        $disk = Storage::disk($this->secureDiskName());

        if (! $disk->exists($sourcePdfPath)) {
            return null;
        }

        try {
            $pdf = new Fpdi;
            $pageCount = $pdf->setSourceFile($disk->path($sourcePdfPath));

            for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
                $templateId = $pdf->importPage($pageNumber);
                $size = $pdf->getTemplateSize($templateId);
                $orientation = $size['width'] > $size['height'] ? 'L' : 'P';

                $pdf->AddPage($orientation, [$size['width'], $size['height']]);
                $pdf->useTemplate($templateId);
            }

            // Add notary seal page
            $pdf->AddPage('P', [215.9, 279.4]); // Letter size
            $this->renderSealPage($pdf, $credential, $entry);

            $outputPath = sprintf(
                'documents/notarized/%s-notarized-%s.pdf',
                $entry->notary_request_id,
                Str::uuid()->toString()
            );

            $disk->put($outputPath, $pdf->Output('S'));

            return $outputPath;
        } catch (Throwable $throwable) {
            report($throwable);

            return null;
        }
    }

    /**
     * Generate a QR code image for the register entry verification.
     */
    public function generateVerificationQrCode(NotarialRegisterEntry $entry): ?string
    {
        $verificationUrl = route('notary.verify', ['token' => $entry->qr_verification_token]);

        // Use Google Charts API for QR code generation (same pattern as existing TOTP QR)
        $qrUrl = sprintf(
            'https://chart.googleapis.com/chart?cht=qr&chs=300x300&chl=%s&choe=UTF-8',
            urlencode($verificationUrl)
        );

        try {
            $qrContent = file_get_contents($qrUrl);
            if ($qrContent === false) {
                return null;
            }

            $path = sprintf('notary/qr/%s.png', $entry->qr_verification_token);
            Storage::disk($this->secureDiskName())->put($path, $qrContent);

            $entry->update(['qr_code_path' => $path]);

            return $path;
        } catch (Throwable) {
            return null;
        }
    }

    private function renderSealPage(Fpdi $pdf, NotaryCredential $credential, NotarialRegisterEntry $entry): void
    {
        $pdf->SetFont('Helvetica', 'B', 14);
        $pdf->SetTextColor(15, 23, 42);
        $pdf->SetXY(20, 20);
        $pdf->Cell(175, 8, 'NOTARIAL CERTIFICATE', 0, 1, 'C');

        $pdf->SetFont('Helvetica', '', 10);
        $pdf->SetXY(20, 35);
        $pdf->MultiCell(175, 5, sprintf(
            "Republic of the Philippines\n%s\n\n".
            "BEFORE ME, a Notary Public for and in the %s, personally appeared:\n",
            $credential->commission_jurisdiction,
            $credential->commission_jurisdiction,
        ));

        $y = $pdf->GetY() + 5;
        $pdf->SetFont('Helvetica', '', 9);

        foreach ($entry->parties as $index => $party) {
            $name = is_array($party) ? ($party['name'] ?? '') : '';
            $address = is_array($party) ? ($party['address'] ?? '') : '';
            $pdf->SetXY(25, $y);
            $pdf->Cell(170, 5, sprintf('%d. %s — %s', $index + 1, $name, $address));
            $y += 6;
        }

        $y += 5;
        $pdf->SetFont('Helvetica', '', 10);
        $pdf->SetXY(20, $y);
        $pdf->MultiCell(175, 5, sprintf(
            'known to me to be the same person(s) who executed the foregoing instrument consisting of '.
            "the document titled \"%s\" and acknowledged to me that the same is their free and voluntary act and deed.\n\n".
            "This instrument refers to a %s.\n\n".
            "WITNESS MY HAND AND SEAL this %s.\n",
            $entry->document_title,
            str_replace('_', ' ', $entry->notarial_act_type),
            $entry->notarized_at?->timezone('Asia/Manila')->format('jS \\d\\a\\y \\o\\f F, Y') ?? now()->format('jS \\d\\a\\y \\o\\f F, Y'),
        ));

        $y = $pdf->GetY() + 10;

        // Notary signature block
        $pdf->SetFont('Helvetica', 'B', 10);
        $pdf->SetXY(110, $y);
        $pdf->Cell(85, 5, $credential->user?->name ?? 'Notary Public', 0, 1, 'C');
        $pdf->SetFont('Helvetica', '', 9);
        $pdf->SetXY(110, $y + 5);
        $pdf->Cell(85, 5, 'Notary Public', 0, 1, 'C');
        $pdf->SetXY(110, $y + 10);
        $pdf->Cell(85, 5, sprintf('Commission No. %s', $credential->commission_number), 0, 1, 'C');
        $pdf->SetXY(110, $y + 15);
        $pdf->Cell(85, 5, sprintf('Until %s', $credential->commission_expires_at?->format('F j, Y') ?? ''), 0, 1, 'C');

        if ($credential->roll_number) {
            $pdf->SetXY(110, $y + 20);
            $pdf->Cell(85, 5, sprintf('Roll No. %s', $credential->roll_number), 0, 1, 'C');
        }

        if ($credential->ibp_number) {
            $pdf->SetXY(110, $y + 25);
            $pdf->Cell(85, 5, sprintf('IBP No. %s', $credential->ibp_number), 0, 1, 'C');
        }

        // Entry reference
        $y = $pdf->GetY() + 15;
        $pdf->SetFont('Helvetica', '', 9);
        $pdf->SetXY(20, $y);
        $pdf->Cell(175, 5, $entry->formattedReference(), 0, 1, 'L');

        // Seal image
        if ($credential->seal_image_path !== null && $credential->seal_image_path !== '') {
            $disk = Storage::disk($this->secureDiskName());
            if ($disk->exists($credential->seal_image_path)) {
                try {
                    $pdf->Image($disk->path($credential->seal_image_path), 25, $y - 30, 40, 40);
                } catch (Throwable) {
                    // Seal image rendering failed, continue without it
                }
            }
        }
    }
}
