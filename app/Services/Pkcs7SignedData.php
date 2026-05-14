<?php

namespace App\Services;

use RuntimeException;

/**
 * PKCS#7 Signed Data Builder
 * 
 * Implements PKCS#7 standard for signed data as required by CSC.
 */
class Pkcs7SignedData
{
    private array $certificates = [];
    private array $signers = [];
    private string $content = '';
    private string $digestAlgorithm = 'sha256';

    /**
     * Add certificate to chain
     *
     * @param string $certificate PEM-encoded certificate
     * @return self
     */
    public function addCertificate(string $certificate): self
    {
        $this->certificates[] = $certificate;
        return $this;
    }

    /**
     * Add signer
     *
     * @param string $certificate PEM-encoded certificate
     * @param string $privateKey PEM-encoded private key
     * @return self
     */
    public function addSigner(string $certificate, string $privateKey): self
    {
        $this->signers[] = [
            'certificate' => $certificate,
            'privateKey' => $privateKey,
        ];
        return $this;
    }

    /**
     * Set content to sign
     *
     * @param string $content
     * @return self
     */
    public function setContent(string $content): self
    {
        $this->content = $content;
        return $this;
    }

    /**
     * Set digest algorithm
     *
     * @param string $algorithm sha256, sha384, sha512
     * @return self
     */
    public function setDigestAlgorithm(string $algorithm): self
    {
        $this->digestAlgorithm = $algorithm;
        return $this;
    }

    /**
     * Sign content and create PKCS#7 signed data
     *
     * @param string $outputFile Output file path (optional)
     * @return string Signed data in PEM format
     */
    public function sign(string $outputFile = null): string
    {
        $certResource = openssl_x509_read($this->certificates[0]);
        $keyResource = openssl_pkey_get_private($this->signers[0]['privateKey']);

        if ($certResource === false || $keyResource === false) {
            throw new RuntimeException('Invalid certificate or private key.');
        }

        $signConfig = [
            'digest_alg' => 'sha256',
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];

        $signedData = '';
        $result = openssl_pkcs7_sign(
            $this->createTempFile($this->content),
            $outputFile ?? $this->createTempFile('signed.p7s'),
            $certResource,
            $keyResource,
            [],
            OPENSSL_PKCS7_BINARY
        );

        if ($result === false) {
            throw new RuntimeException('Failed to create PKCS#7 signed data.');
        }

        if ($outputFile === null) {
            $signedData = file_get_contents($this->createTempFile('signed.p7s'));
            unlink($this->createTempFile('signed.p7s'));
        }

        return $signedData;
    }

    /**
     * Verify PKCS#7 signed data
     *
     * @param string $signedData Signed data in PEM format
     * @param string $certificatesFile CA certificates file
     * @return array{verified: bool, certificates: array, content: string}
     */
    public static function verify(string $signedData, string $certificatesFile = null): array
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'pkcs7_');
        file_put_contents($tempFile, $signedData);

        $verified = openssl_pkcs7_verify(
            $tempFile,
            OPENSSL_PKCS7_NOVERIFY,
            $certificatesFile ?? sys_get_temp_dir() . '/cacert.pem',
            [],
            $verifiedCertificates
        );

        unlink($tempFile);

        return [
            'verified' => $verified === true,
            'certificates' => $verifiedCertificates ?? [],
            'content' => '', // Content extraction requires additional steps
        ];
    }

    private function createTempFile(string $name): string
    {
        return sys_get_temp_dir() . '/' . $name;
    }
}
