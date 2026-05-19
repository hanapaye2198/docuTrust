<?php

namespace App\Services\Ekyc;

use App\Contracts\Ekyc\IdDocumentTextExtractor;
use App\Exceptions\EkycOcrUnavailableException;
use App\Models\User;

class EkycNameVerificationService
{
    public function __construct(
        private IdDocumentTextExtractor $textExtractor,
        private EkycNameMatcher $nameMatcher,
    ) {}

    /**
     * @throws EkycOcrUnavailableException
     */
    public function verify(User $user, string $documentAbsolutePath): EkycNameMatchResult
    {
        $ocrText = $this->textExtractor->extract($documentAbsolutePath);

        return $this->nameMatcher->match($user, $ocrText);
    }
}
