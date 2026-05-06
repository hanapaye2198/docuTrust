<?php

namespace App\Contracts;

use App\Data\SignerSealResult;
use App\Models\DocumentSigner;

interface SignerSealProvider
{
    public function seal(DocumentSigner $signer, string $hash): SignerSealResult;
}
