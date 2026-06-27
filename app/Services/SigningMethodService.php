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
            // AccountVerified signers must be authenticated. The route guard
            // redirects to login (storing the intended URL) if not logged in,
            // so this link works regardless of the signer's current session state.
            SigningMethod::AccountVerified => route('sign.account.show', ['signerId' => $signer->id]),
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
