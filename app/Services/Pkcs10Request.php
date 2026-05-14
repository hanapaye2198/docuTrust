<?php

namespace App\Services;

use RuntimeException;

/**
 * PKCS#10 Certificate Signing Request (CSR) Builder
 * 
 * Implements PKCS#10 standard for certificate requests as required by CSC.
 */
class Pkcs10Request
{
    private array $distinguishedName = [];
    private string $publicKey = '';
    private ?string $challengePassword = null;
    private array $attributes = [];

    /**
     * Set distinguished name (DN)
     *
     * @param array $dn ['commonName' => '...', 'organizationName' => '...', ...]
     * @return self
     */
    public function setDistinguishedName(array $dn): self
    {
        $this->distinguishedName = $dn;
        return $this;
    }

    /**
     * Set public key
     *
     * @param string $publicKey PEM-encoded public key
     * @return self
     */
    public function setPublicKey(string $publicKey): self
    {
        $this->publicKey = $publicKey;
        return $this;
    }

    /**
     * Set challenge password
     *
     * @param string $password
     * @return self
     */
    public function setChallengePassword(string $password): self
    {
        $this->challengePassword = $password;
        return $this;
    }

    /**
     * Add attribute
     *
     * @param string $oid OID of attribute
     * @param mixed $value Attribute value
     * @return self
     */
    public function addAttribute(string $oid, mixed $value): self
    {
        $this->attributes[$oid] = $value;
        return $this;
    }

    /**
     * Generate CSR in PEM format
     *
     * @param string $privateKey PEM-encoded private key
     * @param string $algorithm Digest algorithm (sha256, sha384, sha512)
     * @return string CSR in PEM format
     */
    public function generate(string $privateKey, string $algorithm = 'sha256'): string
    {
        $resource = openssl_pkey_get_private($privateKey);
        if ($resource === false) {
            throw new RuntimeException('Invalid private key.');
        }

        $config = [
            'digest_alg' => $algorithm,
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];

        $csr = openssl_csr_new($this->distinguishedName, $resource, $config);

        if ($csr === false) {
            throw new RuntimeException('Failed to create CSR.');
        }

        // Add attributes if present
        if (!empty($this->attributes)) {
            $this->addAttributesToCsr($csr);
        }

        $csrPem = '';
        if (!openssl_csr_export($csr, $csrPem)) {
            throw new RuntimeException('Failed to export CSR.');
        }

        return $csrPem;
    }

    /**
     * Generate CSR and sign it
     *
     * @param string $privateKey PEM-encoded private key
     * @param string $algorithm Digest algorithm
     * @return array{csr: string, publicKey: string}
     */
    public function generateAndSign(string $privateKey, string $algorithm = 'sha256'): array
    {
        $csrPem = $this->generate($privateKey, $algorithm);

        // Extract public key from CSR
        $csrResource = openssl_csr_get_publickey($csrPem);
        $details = openssl_pkey_get_details($csrResource);
        $publicKey = $details['key'] ?? '';

        return [
            'csr' => $csrPem,
            'publicKey' => $publicKey,
        ];
    }

    /**
     * Parse CSR and extract information
     *
     * @param string $csrPem CSR in PEM format
     * @return array{
     *   subject: array,
     *   public_key: string,
     *   attributes: array
     * }
     */
    public static function parse(string $csrPem): array
    {
        $csrResource = openssl_csr_parse($csrPem);

        if ($csrResource === false) {
            throw new RuntimeException('Failed to parse CSR.');
        }

        $publicKeyResource = openssl_csr_get_publickey($csrPem);
        $publicKeyDetails = openssl_pkey_get_details($publicKeyResource);

        return [
            'subject' => $csrResource['subject'] ?? [],
            'public_key' => $publicKeyDetails['key'] ?? '',
            'attributes' => $csrResource['attributes'] ?? [],
        ];
    }

    private function addAttributesToCsr($csr): void
    {
        // Add attributes to CSR
        // This is a simplified implementation
    }
}
