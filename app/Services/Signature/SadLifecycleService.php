<?php

namespace App\Services\Signature;

use App\Exceptions\SadNotFoundException;
use App\Models\TrustAuthorizationSession;
use Illuminate\Encryption\Encrypter;
use Illuminate\Log\LogManager;
use Illuminate\Support\Facades\DB;
use Throwable;

class SadLifecycleService
{
    public function __construct(
        private readonly Encrypter $encrypter,
        private readonly LogManager $log,
    ) {}

    public function storeSad(
        int $documentId,
        int $signerId,
        string $credentialId,
        string $sad,
        int $ttlSeconds = 300,
    ): TrustAuthorizationSession {
        $session = TrustAuthorizationSession::query()->updateOrCreate(
            [
                'document_id' => $documentId,
                'document_signer_id' => $signerId,
                'credential_id' => $credentialId,
            ],
            [
                'provider_name' => $this->providerName(),
                'authorization_mode' => (string) config('services.remote_signing.csc.authorization_mode', 'explicit'),
                'sad' => $this->encrypter->encrypt($sad),
                'expires_at' => now()->addSeconds($ttlSeconds),
                'completed_at' => now(),
                'consumed_at' => null,
                'status' => 'authorized',
            ]
        );

        $this->log->channel('signature')->info("SAD stored for document {$documentId}, signer {$signerId}");

        return $session;
    }

    public function consumeSad(int $documentId, int $signerId): string
    {
        return DB::transaction(function () use ($documentId, $signerId): string {
            $session = TrustAuthorizationSession::query()
                ->where('document_id', $documentId)
                ->where('document_signer_id', $signerId)
                ->where('status', 'authorized')
                ->whereNull('consumed_at')
                ->where('expires_at', '>', now())
                ->lockForUpdate()
                ->first();

            if ($session === null) {
                throw new SadNotFoundException("No valid SAD found for document {$documentId}, signer {$signerId}");
            }

            $plainSad = $this->encrypter->decrypt((string) $session->sad);

            $session->update([
                'consumed_at' => now(),
                'status' => 'consumed',
            ]);

            $this->log->channel('signature')->info("SAD consumed for document {$documentId}, signer {$signerId}");

            return $plainSad;
        });
    }

    public function isValid(int $documentId, int $signerId): bool
    {
        try {
            return TrustAuthorizationSession::query()
                ->where('document_id', $documentId)
                ->where('document_signer_id', $signerId)
                ->where('status', 'authorized')
                ->whereNull('consumed_at')
                ->where('expires_at', '>', now())
                ->exists();
        } catch (Throwable) {
            return false;
        }
    }

    public function expireOldSessions(): int
    {
        return TrustAuthorizationSession::query()
            ->where('expires_at', '<', now())
            ->whereNotIn('status', ['consumed', 'expired'])
            ->update([
                'status' => 'expired',
            ]);
    }

    private function providerName(): string
    {
        $providerName = trim((string) config('services.remote_signing.provider_name', 'remote_managed'));

        return $providerName !== '' ? $providerName : 'remote_managed';
    }
}
