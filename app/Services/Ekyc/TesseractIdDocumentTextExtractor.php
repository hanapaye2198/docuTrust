<?php

namespace App\Services\Ekyc;

use App\Contracts\Ekyc\IdDocumentTextExtractor;
use App\Exceptions\EkycOcrUnavailableException;
use Illuminate\Support\Facades\Process;

class TesseractIdDocumentTextExtractor implements IdDocumentTextExtractor
{
    public function extract(string $absolutePath): string
    {
        if (! is_readable($absolutePath)) {
            throw new EkycOcrUnavailableException(__('The uploaded ID image could not be read.'));
        }

        $binary = (string) config('ekyc.tesseract_binary', 'tesseract');
        $language = (string) config('ekyc.tesseract_lang', 'eng');

        $result = Process::run([
            $binary,
            $absolutePath,
            'stdout',
            '-l',
            $language,
            '--psm',
            '6',
        ]);

        if (! $result->successful()) {
            throw new EkycOcrUnavailableException(
                __('ID text could not be read. Ensure Tesseract OCR is installed, or contact support.')
            );
        }

        $text = trim($result->output());

        if ($text === '') {
            throw new EkycOcrUnavailableException(__('No text could be detected on the ID image. Please upload a clearer photo.'));
        }

        return $text;
    }
}
