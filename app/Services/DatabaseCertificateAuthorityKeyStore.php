<?php

namespace App\Services;

use App\Contracts\CertificateAuthorityKeyStore;
use App\Models\CertificateAuthority;
use RuntimeException;

class DatabaseCertificateAuthorityKeyStore implements CertificateAuthorityKeyStore
{
    public function privateKeyPemFor(CertificateAuthority $authority): string
    {
        $privateKeyPem = $authority->private_key_pem;

        if (! is_string($privateKeyPem) || trim($privateKeyPem) === '') {
            throw new RuntimeException('Certificate authority private key is unavailable.');
        }

        return $privateKeyPem;
    }

    public function storePrivateKeyPem(string $privateKeyPem): string
    {
        if (trim($privateKeyPem) === '') {
            throw new RuntimeException('Certificate authority private key is unavailable.');
        }

        return $privateKeyPem;
    }
}
