<?php

namespace App\Services;

use App\Contracts\CertificateAuthorityKeyStore;
use App\Models\CertificateAuthority;
use App\Models\SignerCertificate;
use DateTimeImmutable;
use RuntimeException;

/**
 * Certificate Revocation List (CRL) Generator
 *
 * Generates X.509 CRLs using ASN.1/DER encoding.
 * PHP's OpenSSL extension does NOT have CRL generation functions,
 * so we build the CRL structure manually and sign it with openssl_sign().
 */
class CrlGenerator
{
    private int $nextUpdateDays = 7;

    public function __construct(
        private readonly CertificateAuthorityKeyStore $keyStore,
    ) {}

    /**
     * Generate CRL in PEM format.
     */
    public function getPemFormat(): string
    {
        $der = $this->generateDer();
        $base64 = chunk_split(base64_encode($der), 64, "\n");

        return "-----BEGIN X509 CRL-----\n" . $base64 . "-----END X509 CRL-----\n";
    }

    /**
     * Generate CRL in DER format.
     */
    public function getDerFormat(): string
    {
        return $this->generateDer();
    }

    /**
     * Get CRL distribution points.
     */
    public function getDistributionPoints(): array
    {
        return [
            [
                'uri' => rtrim((string) config('app.url'), '/') . '/crl.pem',
                'name' => 'DocuTrust CRL Distribution Point',
            ],
        ];
    }

    /**
     * Get next update date.
     */
    public function getNextUpdate(): DateTimeImmutable
    {
        return (new DateTimeImmutable())->modify("+{$this->nextUpdateDays} days");
    }

    /**
     * Generate DER-encoded CRL.
     *
     * CRL structure (RFC 5280 Section 5.1):
     * CertificateList ::= SEQUENCE {
     *   tbsCertList     TBSCertList,
     *   signatureAlgorithm AlgorithmIdentifier,
     *   signatureValue  BIT STRING
     * }
     */
    private function generateDer(): string
    {
        $ca = CertificateAuthority::query()
            ->where('is_root', true)
            ->where('status', 'active')
            ->first();

        if ($ca === null) {
            throw new RuntimeException('Root CA not found. Cannot generate CRL.');
        }

        $privateKeyPem = $this->keyStore->privateKeyPemFor($ca);
        $privateKey = openssl_pkey_get_private($privateKeyPem);

        if ($privateKey === false) {
            throw new RuntimeException('Unable to load CA private key for CRL signing.');
        }

        // Build TBSCertList
        $tbsCertList = $this->buildTbsCertList($ca);

        // Sign TBSCertList
        $signature = '';
        if (!openssl_sign($tbsCertList, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Unable to sign CRL.');
        }

        // signatureAlgorithm (SHA-256 with RSA)
        $signatureAlgorithm = $this->asn1Sequence(
            $this->asn1Oid('1.2.840.113549.1.1.11') . $this->asn1Null()
        );

        // CertificateList
        return $this->asn1Sequence(
            $tbsCertList .
            $signatureAlgorithm .
            $this->asn1BitString($signature)
        );
    }

    /**
     * Build TBSCertList structure.
     *
     * TBSCertList ::= SEQUENCE {
     *   version          Version OPTIONAL (v2),
     *   signature        AlgorithmIdentifier,
     *   issuer           Name,
     *   thisUpdate       Time,
     *   nextUpdate       Time OPTIONAL,
     *   revokedCertificates SEQUENCE OF OPTIONAL,
     * }
     */
    private function buildTbsCertList(CertificateAuthority $ca): string
    {
        // version (v2 = 1)
        $version = $this->asn1Integer("\x01");

        // signature algorithm (SHA-256 with RSA)
        $signatureAlgorithm = $this->asn1Sequence(
            $this->asn1Oid('1.2.840.113549.1.1.11') . $this->asn1Null()
        );

        // issuer (from CA certificate DN)
        $issuer = $this->buildIssuerDn($ca);

        // thisUpdate
        $thisUpdate = $this->asn1UtcTime(gmdate('ymdHis') . 'Z');

        // nextUpdate
        $nextUpdate = $this->asn1UtcTime(
            gmdate('ymdHis', strtotime("+{$this->nextUpdateDays} days")) . 'Z'
        );

        // revokedCertificates
        $revokedCerts = $this->buildRevokedCertificates();

        $content = $version . $signatureAlgorithm . $issuer . $thisUpdate . $nextUpdate;

        if ($revokedCerts !== '') {
            $content .= $this->asn1Sequence($revokedCerts);
        }

        return $this->asn1Sequence($content);
    }

    /**
     * Build revoked certificates list.
     */
    private function buildRevokedCertificates(): string
    {
        $revoked = SignerCertificate::query()
            ->where(function ($query) {
                $query->whereNotNull('revoked_at')
                    ->orWhere('status', 'revoked');
            })
            ->get();

        $entries = '';

        foreach ($revoked as $cert) {
            $serialHex = $cert->serial_number;
            $serialBytes = hex2bin(str_pad($serialHex, strlen($serialHex) + (strlen($serialHex) % 2), '0', STR_PAD_LEFT));

            if ($serialBytes === false) {
                continue;
            }

            $revokedAt = $cert->revoked_at ?? $cert->updated_at ?? now();
            $revocationTime = $this->asn1UtcTime($revokedAt->format('ymdHis') . 'Z');

            // revokedCertificate ::= SEQUENCE { serialNumber, revocationDate }
            $entries .= $this->asn1Sequence(
                $this->asn1Integer($serialBytes) . $revocationTime
            );
        }

        return $entries;
    }

    /**
     * Build issuer DN from CA subject.
     */
    private function buildIssuerDn(CertificateAuthority $ca): string
    {
        // Parse the subject_dn string back into RDN components
        $parts = array_map('trim', explode(',', $ca->subject_dn));
        $rdnSequence = '';

        foreach ($parts as $part) {
            if (!str_contains($part, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $part, 2);
            $key = trim($key);
            $value = trim($value);

            $oid = match (strtolower($key)) {
                'cn', 'commonname' => '2.5.4.3',
                'o', 'organizationname' => '2.5.4.10',
                'ou', 'organizationalunitname' => '2.5.4.11',
                'c', 'countryname' => '2.5.4.6',
                'l', 'localityname' => '2.5.4.7',
                'st', 'stateorprovincename' => '2.5.4.8',
                default => null,
            };

            if ($oid === null) {
                continue;
            }

            $atv = $this->asn1Sequence(
                $this->asn1Oid($oid) . $this->asn1Utf8String($value)
            );

            $rdnSequence .= $this->asn1Set($atv);
        }

        return $this->asn1Sequence($rdnSequence);
    }

    // ASN.1 DER encoding helpers

    private function asn1Sequence(string $content): string
    {
        return "\x30" . $this->asn1Length(strlen($content)) . $content;
    }

    private function asn1Set(string $content): string
    {
        return "\x31" . $this->asn1Length(strlen($content)) . $content;
    }

    private function asn1Integer(string $value): string
    {
        if (strlen($value) > 0 && (ord($value[0]) & 0x80)) {
            $value = "\x00" . $value;
        }
        return "\x02" . $this->asn1Length(strlen($value)) . $value;
    }

    private function asn1BitString(string $value): string
    {
        $content = "\x00" . $value; // 0 unused bits
        return "\x03" . $this->asn1Length(strlen($content)) . $content;
    }

    private function asn1OctetString(string $value): string
    {
        return "\x04" . $this->asn1Length(strlen($value)) . $value;
    }

    private function asn1Null(): string
    {
        return "\x05\x00";
    }

    private function asn1Utf8String(string $value): string
    {
        return "\x0C" . $this->asn1Length(strlen($value)) . $value;
    }

    private function asn1UtcTime(string $time): string
    {
        return "\x17" . $this->asn1Length(strlen($time)) . $time;
    }

    private function asn1Oid(string $oid): string
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

        return "\x06" . $this->asn1Length(strlen($encoded)) . $encoded;
    }

    private function asn1Length(int $length): string
    {
        if ($length < 0x80) {
            return chr($length);
        }

        $bytes = '';
        $temp = $length;
        while ($temp > 0) {
            $bytes = chr($temp & 0xFF) . $bytes;
            $temp >>= 8;
        }

        return chr(0x80 | strlen($bytes)) . $bytes;
    }
}
