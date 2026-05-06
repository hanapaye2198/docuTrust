<?php

namespace App\Services;

use App\Contracts\SignerSealProvider;
use App\Data\SignerSealResult;
use App\Models\DocumentSigner;
use App\Trust\RemoteSigning\RemoteSigningClient;
use RuntimeException;

class RemoteManagedSignerSealProvider implements SignerSealProvider
{
    public function __construct(
        private readonly PkiSignatureService $pkiSignatureService,
        private readonly SignerCertificateService $signerCertificateService,
        private readonly RemoteSigningClient $remoteSigningClient,
    ) {}

    public function seal(DocumentSigner $signer, string $hash): SignerSealResult
    {
        $providerName = trim((string) config('services.remote_signing.provider_name', 'remote_managed'));
        $material = $this->remoteSigningClient->signHash($signer, $hash);

        $certificate = $this->signerCertificateService->createOrRefreshProviderManagedForSigner(
            $signer,
            providerName: $providerName !== '' ? $providerName : 'remote_managed',
            providerReference: $material->providerReference,
            certificatePem: $material->certificatePem,
            issuerCertificatePem: $material->issuerCertificatePem,
            publicKeyPem: $material->publicKeyPem,
        );

        if (! $this->pkiSignatureService->verifySignature($hash, $material->signatureValue, $certificate->public_key_pem)) {
            throw new RuntimeException('Remote signing provider returned a signature that could not be verified.');
        }

        return new SignerSealResult(
            signerCertificateId: $certificate->id,
            signatureValue: $material->signatureValue,
            signatureHash: $hash,
            publicKeyFingerprint: $this->pkiSignatureService->fingerprint($certificate->public_key_pem),
            signatureAlgorithm: $material->signatureAlgorithm,
            signingProvider: $providerName !== '' ? $providerName : 'remote_managed',
            signingProviderReference: $material->providerReference,
            signingProviderPayload: $material->evidence,
        );
    }
}
