<?php

namespace App\Services;

use App\Contracts\CertificateAuthorityKeyStore;
use App\Models\CertificateAuthority;
use Illuminate\Support\Facades\File;
use RuntimeException;

class FileBackedCertificateAuthorityKeyStore implements CertificateAuthorityKeyStore
{
    public function __construct(
        private readonly DatabaseCertificateAuthorityKeyStore $databaseKeyStore,
    ) {}

    public function privateKeyPemFor(CertificateAuthority $authority): string
    {
        $path = $this->configuredPath();

        if ($path !== null && File::exists($path)) {
            $privateKeyPem = File::get($path);

            if (trim($privateKeyPem) !== '') {
                return $privateKeyPem;
            }
        }

        return $this->databaseKeyStore->privateKeyPemFor($authority);
    }

    public function storePrivateKeyPem(string $privateKeyPem): string
    {
        $path = $this->configuredPath();
        if ($path === null) {
            return $this->databaseKeyStore->storePrivateKeyPem($privateKeyPem);
        }

        $directory = dirname($path);
        if (! File::isDirectory($directory)) {
            File::ensureDirectoryExists($directory, 0700, true);
        }

        File::put($path, $privateKeyPem);

        return 'external://root-ca';
    }

    private function configuredPath(): ?string
    {
        $path = trim((string) config('docutrust.pki.root_ca_private_key_path', ''));

        return $path !== '' ? $path : null;
    }
}
