<?php

namespace App\Console\Commands;

use App\Contracts\CertificateAuthorityKeyStore;
use App\Models\CertificateAuthority;
use Illuminate\Console\Command;
use RuntimeException;

class MoveRootCaKeyToExternalStore extends Command
{
    protected $signature = 'docutrust:move-root-ca-key {--force : Overwrite an existing external key file}';

    protected $description = 'Move the active root CA private key from the database to the configured external key store path.';

    public function handle(CertificateAuthorityKeyStore $certificateAuthorityKeyStore): int
    {
        $path = trim((string) config('docutrust.pki.root_ca_private_key_path', ''));
        if ($path === '') {
            $this->error('DOCUTRUST_ROOT_CA_PRIVATE_KEY_PATH is not configured.');

            return self::FAILURE;
        }

        $authority = CertificateAuthority::query()
            ->where('is_root', true)
            ->where('status', 'active')
            ->first();

        if ($authority === null) {
            $this->error('No active root certificate authority was found.');

            return self::FAILURE;
        }

        if (! is_string($authority->private_key_pem) || trim($authority->private_key_pem) === '') {
            $this->error('The active root certificate authority does not have a stored private key.');

            return self::FAILURE;
        }

        if ($authority->private_key_pem === 'external://root-ca') {
            $this->info('The active root certificate authority is already using the external key marker.');

            return self::SUCCESS;
        }

        if (is_file($path) && ! $this->option('force')) {
            $this->error('The configured external key file already exists. Re-run with --force to overwrite it.');

            return self::FAILURE;
        }

        try {
            $marker = $certificateAuthorityKeyStore->storePrivateKeyPem($authority->private_key_pem);
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($marker !== 'external://root-ca') {
            $this->error('The configured key store did not return the expected external root CA marker.');

            return self::FAILURE;
        }

        $authority->forceFill([
            'private_key_pem' => $marker,
        ])->save();

        $this->info(sprintf('Moved root CA private key to external store at %s.', $path));

        return self::SUCCESS;
    }
}
