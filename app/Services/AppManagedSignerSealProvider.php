<?php

namespace App\Services;

use App\Contracts\SignerKeyStore;
use App\Contracts\SignerSealProvider;
use App\Data\SignerSealResult;
use App\Models\DocumentSigner;
use RuntimeException;

class AppManagedSignerSealProvider implements SignerSealProvider
{
    public function __construct(
        private readonly PkiSignatureService $pkiSignatureService,
        private readonly SignerCertificateService $signerCertificateService,
        private readonly SignerKeyStore $signerKeyStore,
    ) {}

    public function seal(DocumentSigner $signer, string $hash): SignerSealResult
    {
        $signerKeyPair = $this->signerKeyStore->keyPairFor($signer);

        $certificate = $this->signerCertificateService->getOrIssueForSigner($signer);
        $signatureValue = $this->pkiSignatureService->signHash($hash, $signerKeyPair['private_key']);

        if (! $this->pkiSignatureService->verifySignature($hash, $signatureValue, $signerKeyPair['public_key'])) {
            throw new RuntimeException('Digital signature verification failed during final document sealing.');
        }

        return new SignerSealResult(
            signerCertificateId: $certificate->id,
            signatureValue: $signatureValue,
            signatureHash: $hash,
            publicKeyFingerprint: $this->pkiSignatureService->fingerprint($signerKeyPair['public_key']),
            signatureAlgorithm: 'RSA-SHA256',
            signingProvider: 'app_managed',
            signingProviderReference: null,
            signingProviderPayload: null,
        );
    }
}
