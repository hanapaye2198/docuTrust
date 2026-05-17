<?php

namespace App\Services;

use App\Contracts\HsmService;
use App\Contracts\SignerSealProvider;
use App\Data\SignerSealResult;
use App\Models\DocumentSigner;
use RuntimeException;

/**
 * HSM-backed Signer Seal Provider
 * 
 * Implements CSC-compliant document sealing using HSM for signature operations.
 */
class HsmSignerSealProvider implements SignerSealProvider
{
    public function __construct(
        private readonly HsmPkiSignatureService $pkiSignatureService,
        private readonly HsmKeyManager $hsmKeyManager,
        private readonly SignerCertificateService $signerCertificateService,
    ) {}

    /**
     * Seal document signatures using HSM.
     */
    public function seal(DocumentSigner $signer, string $hash): SignerSealResult
    {
        // Ensure HSM key exists for signer
        if (!$this->hsmKeyManager->hasKeyPair($signer)) {
            $this->hsmKeyManager->generateKeyPairForSigner($signer);
            $signer->refresh();
        }

        $keyPair = $this->hsmKeyManager->keyPairFor($signer);

        // Get or issue certificate for signer
        $certificate = $this->signerCertificateService->getOrIssueForSigner($signer);

        // Sign hash using HSM (private_key holds the HSM key ID)
        $signatureValue = $this->pkiSignatureService->signHash(
            $hash,
            $keyPair['private_key']
        );

        // Verify signature (integrity check)
        if (!$this->pkiSignatureService->verifySignature(
            $hash,
            $signatureValue,
            $keyPair['private_key']
        )) {
            throw new RuntimeException('Digital signature verification failed during sealing.');
        }

        // Generate public key fingerprint
        $publicKeyFingerprint = $this->pkiSignatureService->fingerprint(
            $certificate->public_key_pem
        );

        return new SignerSealResult(
            signerCertificateId: $certificate->id,
            signatureValue: $signatureValue,
            signatureHash: hash('sha256', $signatureValue),
            publicKeyFingerprint: $publicKeyFingerprint,
            signatureAlgorithm: 'RSA-SHA256',
            signingProvider: 'hsm_managed',
            signingProviderReference: $signer->hsm_key_id,
            signingProviderPayload: [
                'hsm_backend' => config('hsm.backend', 'mock'),
                'key_id' => $signer->hsm_key_id,
                'timestamp' => now()->toIso8601String(),
            ],
        );
    }
}
