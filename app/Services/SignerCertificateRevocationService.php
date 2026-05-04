<?php

namespace App\Services;

use App\Models\SignerCertificate;

class SignerCertificateRevocationService
{
    public function revoke(SignerCertificate $certificate, string $reason): SignerCertificate
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw new \InvalidArgumentException('A revocation reason is required.');
        }

        $certificate->forceFill([
            'status' => 'revoked',
            'revoked_at' => $certificate->revoked_at ?? now(),
            'revocation_reason' => $reason,
        ])->save();

        return $certificate->refresh();
    }
}
