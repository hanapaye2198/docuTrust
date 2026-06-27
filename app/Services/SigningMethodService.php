<?php

namespace App\Services;

use App\Enums\SigningMethod;
use App\Models\DocumentSigner;
use App\Models\User;
use RuntimeException;

class SigningMethodService
{
    public function requiresAuthenticatedAccount(DocumentSigner $signer): bool
    {
        return $signer->signingMethod() === SigningMethod::AccountVerified;
    }

    public function requiresTrustAuthorization(DocumentSigner $signer): bool
    {
        return $signer->signingMethod() === SigningMethod::PkiCertificate
            && (string) config('docutrust.pki.signing_backend', 'app_managed') === 'remote_managed';
    }

    public function canAuthenticatedUserAccessSigner(DocumentSigner $signer, ?User $user): bool
    {
        if (! $this->requiresAuthenticatedAccount($signer)) {
            return true;
        }

        if ($user === null || $signer->user_id === null) {
            return false;
        }

        return $user->getKey() === $signer->user_id;
    }

    public function signerEntryUrl(DocumentSigner $signer): string
    {
        return match ($signer->signingMethod()) {
            // AccountVerified signers log into their own DocuTrust account.
            // The email just points them to the app — the dashboard notifies
            // them of pending documents once they are logged in.
            SigningMethod::AccountVerified => rtrim((string) config('app.url'), '/'),
            default => route('sign.show', $this->publicSigningToken($signer)),
        };
    }

    public function signerCompletedDocumentUrl(DocumentSigner $signer): ?string
    {
        if ($signer->signingMethod() === SigningMethod::AccountVerified) {
            return route('sign.account.show', ['signerId' => $signer->id]);
        }

        $token = $signer->access_token;
        if (! is_string($token) || $token === '') {
            return null;
        }

        return route('sign.document.download', ['token' => $token]);
    }

    public function publicSigningToken(DocumentSigner $signer): string
    {
        $token = $signer->access_token;
        if (! is_string($token) || $token === '') {
            throw new RuntimeException('Signer access token is missing.');
        }

        return $token;
    }
}
