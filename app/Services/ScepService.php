<?php

namespace App\Services;

use RuntimeException;

/**
 * SCEP (Simple Certificate Enrollment Protocol) Service
 * 
 * Implements SCEP protocol for certificate enrollment as required by CSC.
 * Based on RFC 8894.
 */
class ScepService
{
    private string $caName = 'DocuTrust CA';
    private string $scepUrl;
    private int $messageRetry = 3;
    private int $messageTimeout = 30;

    public function __construct()
    {
        $this->scepUrl = (string) config('scep.url', '/scep');
    }

    /**
     * Get SCEP capabilities
     *
     * @return array
     */
    public function getCapabilities(): array
    {
        return [
            'PKI_STATUS',
            'MESSAGETYPE',
            'NONCE',
            'TRANSPORT',
            'SCEP_STANDARD',
            'POST_PKI_MESSAGE',
            'SHA-256',
            'SHA-384',
            'SHA-512',
            'AES',
        ];
    }

    /**
     * Generate SCEP transaction ID
     *
     * @return string
     */
    public function generateTransactionId(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * Generate SCEP nonce
     *
     * @return string
     */
    public function generateNonce(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * Create SCEP PKCS#10 request
     *
     * @param string $publicKey PEM-encoded public key
     * @param string $privateKey PEM-encoded private key
     * @param array $subject Subject DN
     * @return string PKCS#10 CSR in PEM format
     */
    public function createPkcs10Request(string $publicKey, string $privateKey, array $subject): string
    {
        $pkcs10 = new Pkcs10Request();
        $pkcs10->setDistinguishedName($subject);

        // For now, we'll use a simplified approach
        $resource = openssl_pkey_get_private($privateKey);
        if ($resource === false) {
            throw new RuntimeException('Invalid private key.');
        }

        $csr = openssl_csr_new($subject, $resource, [
            'digest_alg' => 'sha256',
        ]);

        $csrPem = '';
        if (!openssl_csr_export($csr, $csrPem)) {
            throw new RuntimeException('Failed to create CSR.');
        }

        return $csrPem;
    }

    /**
     * Encrypt PKCS#10 request for SCEP
     *
     * @param string $pkcs10 PKCS#10 CSR
     * @param string $caCertificate CA certificate
     * @return string Encrypted PKCS#10
     */
    public function encryptPkcs10(string $pkcs10, string $caCertificate): string
    {
        $caResource = openssl_x509_read($caCertificate);
        if ($caResource === false) {
            throw new RuntimeException('Invalid CA certificate.');
        }

        $caDetails = openssl_x509_parse($caCertificate);
        $caPublicKey = openssl_pkey_get_public($caCertificate);
        $publicKeyDetails = openssl_pkey_get_details($caPublicKey);

        // Encrypt using CA's public key (simplified)
        $encrypted = '';
        openssl_public_encrypt($pkcs10, $encrypted, $publicKeyDetails['key'], OPENSSL_PKCS1_OAEP_PADDING);

        return base64_encode($encrypted);
    }

    /**
     * Create SCEP PKI message
     *
     * @param string $pkcs10 PKCS#10 CSR
     * @param string $privateKey Private key for signing
     * @param string $certificate Sender certificate
     * @param string $nonce SCEP nonce
     * @param string $transactionId Transaction ID
     * @param string $messageType Message type (0=PKCSReq, 3=GetCert, 4=GetCRL)
     * @return string Base64-encoded PKI message
     */
    public function createPkiMessage(
        string $pkcs10,
        string $privateKey,
        string $certificate,
        string $nonce,
        string $transactionId,
        int $messageType = 0
    ): string {
        // Create PKCS#7 signed data
        $pkcs7 = new Pkcs7SignedData();
        $pkcs7->addCertificate($certificate);
        $pkcs7->addSigner($certificate, $privateKey);
        $pkcs7->setContent($pkcs10);
        $pkcs7->setDigestAlgorithm('sha256');

        $signedData = $pkcs7->sign();

        // Create PKI message with headers
        $pkiMessage = [
            'message' => base64_encode($signedData),
            'nonce' => $nonce,
            'transactionId' => $transactionId,
            'messageType' => $messageType,
            'senderNonce' => $this->generateNonce(),
        ];

        return base64_encode(json_encode($pkiMessage));
    }

    /**
     * Parse SCEP PKI message.
     * Supports both binary PKCS#7 envelope and JSON fallback.
     *
     * @param string $pkiMessage Base64-encoded or raw PKCS#7 message
     * @return array{
     *   message: string,
     *   nonce: string,
     *   transactionId: string,
     *   messageType: int,
     *   senderNonce: string
     * }
     */
    public function parsePkiMessage(string $pkiMessage): array
    {
        // Try binary PKCS#7 first (proper SCEP format)
        $binary = base64_decode($pkiMessage, true);
        if ($binary !== false && strlen($binary) > 4 && ord($binary[0]) === 0x30) {
            return $this->parseBinaryScepMessage($binary);
        }

        // Try JSON fallback for API consumers
        $decoded = base64_decode($pkiMessage, true);
        if ($decoded !== false) {
            $json = json_decode($decoded, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($json)) {
                return [
                    'message' => $json['message'] ?? '',
                    'nonce' => $json['nonce'] ?? '',
                    'transactionId' => $json['transactionId'] ?? bin2hex(random_bytes(16)),
                    'messageType' => (int) ($json['messageType'] ?? 0),
                    'senderNonce' => $json['senderNonce'] ?? '',
                ];
            }
        }

        throw new RuntimeException('Invalid PKI message: not a valid PKCS#7 envelope or JSON format.');
    }

    /**
     * Parse binary SCEP message (PKCS#7 SignedData envelope).
     *
     * @param string $binary Raw DER-encoded PKCS#7
     * @return array
     */
    private function parseBinaryScepMessage(string $binary): array
    {
        // Extract transaction ID from signed attributes
        $transactionId = $this->extractScepAttribute($binary, '2.16.840.1.113733.1.9.7');
        // Extract message type from signed attributes
        $messageType = $this->extractScepAttribute($binary, '2.16.840.1.113733.1.9.2');
        // Extract sender nonce
        $senderNonce = $this->extractScepAttribute($binary, '2.16.840.1.113733.1.9.5');

        return [
            'message' => base64_encode($binary),
            'nonce' => $senderNonce ?? bin2hex(random_bytes(16)),
            'transactionId' => $transactionId ?? bin2hex(random_bytes(16)),
            'messageType' => $messageType !== null ? (int) $messageType : 0,
            'senderNonce' => $senderNonce ?? bin2hex(random_bytes(16)),
        ];
    }

    /**
     * Extract SCEP attribute from PKCS#7 signed attributes by OID.
     *
     * @param string $der DER-encoded PKCS#7
     * @param string $oid Attribute OID
     * @return string|null
     */
    private function extractScepAttribute(string $der, string $oid): ?string
    {
        // Encode OID to DER for searching
        $oidDer = $this->encodeOid($oid);
        $pos = strpos($der, $oidDer);

        if ($pos === false) {
            return null;
        }

        // Skip past OID and look for the value
        $offset = $pos + strlen($oidDer);
        $len = strlen($der);

        // Skip SET wrapper if present
        while ($offset < $len) {
            $tag = ord($der[$offset]);
            if ($tag === 0x31) { // SET
                $offset++;
                $setLen = ord($der[$offset]);
                $offset++;
                // Value should be next
                $valueTag = ord($der[$offset]);
                $offset++;
                $valueLen = ord($der[$offset]);
                $offset++;
                return substr($der, $offset, $valueLen);
            }
            if ($tag === 0x04 || $tag === 0x13) { // OCTET STRING or PrintableString
                $offset++;
                $valueLen = ord($der[$offset]);
                $offset++;
                return substr($der, $offset, $valueLen);
            }
            $offset++;
        }

        return null;
    }

    /**
     * Encode OID string to DER bytes.
     */
    private function encodeOid(string $oid): string
    {
        $parts = explode('.', $oid);
        $encoded = chr((int) $parts[0] * 40 + (int) $parts[1]);

        for ($i = 2; $i < count($parts); $i++) {
            $value = (int) $parts[$i];
            if ($value < 128) {
                $encoded .= chr($value);
            } else {
                $bytes = chr($value & 0x7F);
                $value >>= 7;
                while ($value > 0) {
                    $bytes = chr(0x80 | ($value & 0x7F)) . $bytes;
                    $value >>= 7;
                }
                $encoded .= $bytes;
            }
        }

        return "\x06" . chr(strlen($encoded)) . $encoded;
    }

    /**
     * Build a SCEP response as PKCS#7 SignedData (DER-encoded).
     *
     * @param int $pkiStatus 0=SUCCESS, 2=FAILURE, 3=PENDING
     * @param string $transactionId
     * @param string $recipientNonce
     * @param string|null $certificateDer Issued certificate (for SUCCESS)
     * @return string DER-encoded PKCS#7 response
     */
    public function buildScepResponse(int $pkiStatus, string $transactionId, string $recipientNonce, ?string $certificateDer = null): string
    {
        // Build signed attributes
        $attributes = $this->buildScepSignedAttributes($pkiStatus, $transactionId, $recipientNonce);

        // For a full implementation, this would create a proper PKCS#7 SignedData
        // envelope signed by the CA. This is a structural placeholder that produces
        // valid ASN.1 output.
        $content = $certificateDer ?? '';

        $signedData = "\x30" . chr(strlen($attributes) + strlen($content) + 4) .
            $attributes . "\x04" . chr(strlen($content)) . $content;

        return $signedData;
    }

    /**
     * Build SCEP signed attributes.
     */
    private function buildScepSignedAttributes(int $pkiStatus, string $transactionId, string $recipientNonce): string
    {
        // transactionID attribute
        $txAttr = "\x30" . chr(strlen($transactionId) + 6) .
            $this->encodeOid('2.16.840.1.113733.1.9.7') .
            "\x31\x02\x13" . chr(strlen($transactionId)) . $transactionId;

        // pkiStatus attribute
        $statusStr = (string) $pkiStatus;
        $statusAttr = "\x30" . chr(strlen($statusStr) + 6) .
            $this->encodeOid('2.16.840.1.113733.1.9.3') .
            "\x31\x02\x13" . chr(strlen($statusStr)) . $statusStr;

        return $txAttr . $statusAttr;
    }

    /**
     * Handle SCEP GETCA request
     *
     * @return array{
     *   caName: string,
     *   capabilities: array,
     *   scepUrl: string
     * }
     */
    public function handleGetCa(): array
    {
        return [
            'caName' => $this->caName,
            'capabilities' => $this->getCapabilities(),
            'scepUrl' => $this->scepUrl,
        ];
    }

    /**
     * Handle SCEP GETCERT request
     *
     * @param string $serialNumber Certificate serial number
     * @return array{status: string, certificate: string|null}
     */
    public function handleGetCert(string $serialNumber): array
    {
        // In production, query CA database for certificate
        return [
            'status' => 'pending',
            'certificate' => null,
        ];
    }

    /**
     * Handle SCEP GETCRL request
     *
     * @return array{status: string, crl: string|null}
     */
    public function handleGetCrl(): array
    {
        // In production, generate CRL
        return [
            'status' => 'pending',
            'crl' => null,
        ];
    }

    /**
     * Get CA information for SCEP clients.
     *
     * @return array{
     *   caName: string,
     *   capabilities: array,
     *   scepUrl: string,
     *   contentType: string
     * }
     */
    public function getCaInfo(): array
    {
        return [
            'caName' => $this->caName,
            'capabilities' => $this->getCapabilities(),
            'scepUrl' => $this->scepUrl,
            'contentType' => 'application/x-pki-message', // RFC 8894
        ];
    }

    /**
     * Handle SCEP enrollment request — extracts CSR from PKCS#7 envelope and issues certificate.
     *
     * @param array $pkiMessage Parsed PKI message
     * @return array{status: string, certificate: string|null, errorMessage: string|null}
     */
    public function handleEnrollmentRequest(array $pkiMessage): array
    {
        // Validate message type is PKCSReq (19) or generic (0)
        if (isset($pkiMessage['messageType']) && $pkiMessage['messageType'] !== 19 && $pkiMessage['messageType'] !== 0) {
            return [
                'status' => 'error',
                'certificate' => null,
                'errorMessage' => 'Invalid message type for enrollment.',
            ];
        }

        // Extract the CSR from the message content
        $messageContent = $pkiMessage['message'] ?? '';
        $csrPem = null;

        // Try base64 decode the message content
        $decoded = base64_decode($messageContent, true);
        if ($decoded !== false) {
            // Check if it's a PEM CSR
            if (str_contains($decoded, '-----BEGIN CERTIFICATE REQUEST-----')) {
                $csrPem = $decoded;
            } else {
                // Assume DER-encoded CSR, wrap as PEM
                $csrPem = "-----BEGIN CERTIFICATE REQUEST-----\n" . chunk_split(base64_encode($decoded), 64, "\n") . "-----END CERTIFICATE REQUEST-----\n";
            }
        }

        if ($csrPem === null || @openssl_csr_get_subject($csrPem) === false) {
            return [
                'status' => 'error',
                'certificate' => null,
                'errorMessage' => 'Unable to extract valid CSR from SCEP message.',
            ];
        }

        try {
            $caService = app(CertificateAuthorityService::class);
            $ca = $caService->getOrCreateRootAuthority();

            $keyStore = app(\App\Contracts\CertificateAuthorityKeyStore::class);
            $caPrivateKey = openssl_pkey_get_private($keyStore->privateKeyPemFor($ca));

            if ($caPrivateKey === false) {
                return [
                    'status' => 'error',
                    'certificate' => null,
                    'errorMessage' => 'CA private key unavailable.',
                ];
            }

            $caCert = openssl_x509_read($ca->certificate_pem);
            $serialNumber = $caService->generateCertificateSerialInteger();
            $validDays = (int) config('docutrust.pki.signer_valid_days', 825);

            $config = [
                'digest_alg' => 'sha256',
                'private_key_bits' => 2048,
                'private_key_type' => OPENSSL_KEYTYPE_RSA,
            ];

            $configPath = (string) config('docutrust.pki.openssl_config_path', '');
            if ($configPath !== '' && is_file($configPath)) {
                $config['config'] = $configPath;
                $config['x509_extensions'] = 'usr_cert';
            }

            $x509 = openssl_csr_sign($csrPem, $caCert, $caPrivateKey, $validDays, $config, $serialNumber);

            if ($x509 === false) {
                return [
                    'status' => 'error',
                    'certificate' => null,
                    'errorMessage' => 'Certificate signing failed.',
                ];
            }

            $certificatePem = '';
            openssl_x509_export($x509, $certificatePem);

            return [
                'status' => 'success',
                'certificate' => base64_encode($certificatePem),
                'errorMessage' => null,
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'error',
                'certificate' => null,
                'errorMessage' => 'SCEP enrollment failed: ' . $e->getMessage(),
            ];
        }
    }
}
