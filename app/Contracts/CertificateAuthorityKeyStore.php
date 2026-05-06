<?php

namespace App\Contracts;

use App\Models\CertificateAuthority;

interface CertificateAuthorityKeyStore
{
    public function privateKeyPemFor(CertificateAuthority $authority): string;

    public function storePrivateKeyPem(string $privateKeyPem): string;
}
