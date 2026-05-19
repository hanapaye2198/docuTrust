<?php

namespace App\Contracts\Ekyc;

use App\Exceptions\EkycOcrUnavailableException;

interface IdDocumentTextExtractor
{
    /**
     * @throws EkycOcrUnavailableException
     */
    public function extract(string $absolutePath): string;
}
