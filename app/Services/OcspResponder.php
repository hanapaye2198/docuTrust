<?php

namespace App\Services;

use App\Models\SignerCertificate;
use App\Models\CertificateAuthority;
use RuntimeException;

/**
 * OCSP (Online Certificate Status Protocol) Responder
 *
 * Implements RFC 6960 for real-time certificate status checking.
 * Required for PKI-aware application interoperability (browsers, VPNs, email clients).
 */
class OcspResponder
{
    private const STATUS_GOOD = 0;
    private const STATUS_REVOKED = 1;
    private const STATUS_UNKNOWN = 2;

    private const REASON_UNSPECIFIED = 0;
    private const REASON_KEY_COMPROMISE = 1;
    private const REASON_CA_COMPROMISE = 2;
    private const REASON_AFFILIATION_CHANGED = 3;
    private const REASON_SUPERSEDED = 4;
    private const REASON_CESSATION_OF_OPERATION = 5;
    private const REASON_CERTIFICATE_HOLD = 6;

    /**
     * Process an OCSP request and return a response.
     *
     * @param string $requestDer DER-encoded OCSP request
     * @return string DER-encoded OCSP response
     */
    public function handleRequest(string $requestDer): string
    {
        $parsed = $this->parseOcspRequest($requestDer);

        if ($parsed === null) {
            return $this->buildMalformedResponse();
        }

        $responses = [];
        foreach ($parsed['certIds'] as $certId) {
            $responses[] = $this->checkCertificateStatus($certId);
        }

        return $this->buildOcspResponse($responses, $parsed);
    }

    /**
     * Check certificate status by serial number.
     *
     * @param string $serialNumber Certificate serial number (hex)
     * @return array{status: int, serial: string, revoked_at: ?string, reason: ?int}
     */
    public function checkBySerial(string $serialNumber): array
    {
        $normalizedSerial = strtoupper(trim($serialNumber));

        $certificate = SignerCertificate::query()
            ->where('serial_number', $normalizedSerial)
            ->first();

        if ($certificate === null) {
            return [
                'status' => self::STATUS_UNKNOWN,
                'serial' => $normalizedSerial,
                'revoked_at' => null,
                'reason' => null,
            ];
        }

        if ($certificate->status === 'revoked' || $certificate->revoked_at !== null) {
            return [
                'status' => self::STATUS_REVOKED,
                'serial' => $normalizedSerial,
                'revoked_at' => $certificate->revoked_at?->toIso8601String(),
                'reason' => $this->mapRevocationReason($certificate->revocation_reason),
            ];
        }

        if ($certificate->valid_to !== null && $certificate->valid_to->isPast()) {
            return [
                'status' => self::STATUS_REVOKED,
                'serial' => $normalizedSerial,
                'revoked_at' => $certificate->valid_to->toIso8601String(),
                'reason' => self::REASON_CESSATION_OF_OPERATION,
            ];
        }

        return [
            'status' => self::STATUS_GOOD,
            'serial' => $normalizedSerial,
            'revoked_at' => null,
            'reason' => null,
        ];
    }

    /**
     * Parse OCSP request (DER-encoded).
     *
     * @param string $requestDer
     * @return array|null
     */
    private function parseOcspRequest(string $requestDer): ?array
    {
        if (strlen($requestDer) < 10) {
            return null;
        }

        // Parse ASN.1 SEQUENCE tag
        $offset = 0;
        $tag = ord($requestDer[$offset]);
        if ($tag !== 0x30) { // SEQUENCE
            return null;
        }
        $offset++;

        $length = $this->parseAsn1Length($requestDer, $offset);
        if ($length === null) {
            return null;
        }

        // Extract certificate IDs from the request
        // OCSPRequest ::= SEQUENCE { tbsRequest TBSRequest, optionalSignature ... }
        // TBSRequest ::= SEQUENCE { version, requestorName, requestList, requestExtensions }
        // requestList ::= SEQUENCE OF Request
        // Request ::= SEQUENCE { reqCert CertID, singleRequestExtensions }
        // CertID ::= SEQUENCE { hashAlgorithm, issuerNameHash, issuerKeyHash, serialNumber }

        $certIds = $this->extractCertIdsFromDer($requestDer);

        if (empty($certIds)) {
            return null;
        }

        return [
            'certIds' => $certIds,
            'nonce' => $this->extractNonce($requestDer),
        ];
    }

    /**
     * Extract certificate IDs from DER-encoded OCSP request.
     *
     * @param string $der
     * @return array
     */
    private function extractCertIdsFromDer(string $der): array
    {
        $certIds = [];

        // Search for serial numbers in the DER structure
        // Serial numbers are INTEGER types (tag 0x02) within CertID sequences
        $offset = 0;
        $len = strlen($der);

        while ($offset < $len - 4) {
            // Look for INTEGER tag that could be a serial number
            if (ord($der[$offset]) === 0x02) {
                $intLen = ord($der[$offset + 1]);
                if ($intLen > 0 && $intLen < 32 && ($offset + 2 + $intLen) <= $len) {
                    $serialBytes = substr($der, $offset + 2, $intLen);
                    $serialHex = strtoupper(bin2hex($serialBytes));

                    // Filter out very short integers (likely version numbers)
                    if (strlen($serialHex) >= 4) {
                        $certIds[] = [
                            'serial' => $serialHex,
                            'issuerNameHash' => null,
                            'issuerKeyHash' => null,
                        ];
                    }
                }
            }
            $offset++;
        }

        return $certIds;
    }

    /**
     * Check certificate status for a given CertID.
     *
     * @param array $certId
     * @return array
     */
    private function checkCertificateStatus(array $certId): array
    {
        return $this->checkBySerial($certId['serial']);
    }

    /**
     * Build OCSP response (DER-encoded).
     *
     * @param array $responses
     * @param array $request
     * @return string
     */
    private function buildOcspResponse(array $responses, array $request): string
    {
        $ca = CertificateAuthority::query()
            ->where('is_root', true)
            ->where('status', 'active')
            ->first();

        if ($ca === null) {
            return $this->buildInternalErrorResponse();
        }

        // Build BasicOCSPResponse
        $responseData = $this->buildResponseData($responses, $request);

        // Sign with CA key
        $signature = $this->signResponse($responseData, $ca);

        // Wrap in OCSPResponse
        return $this->wrapOcspResponse($responseData, $signature, $ca);
    }

    /**
     * Build response data (tbsResponseData).
     *
     * @param array $responses
     * @param array $request
     * @return string DER-encoded tbsResponseData
     */
    private function buildResponseData(array $responses, array $request): string
    {
        $singleResponses = '';

        foreach ($responses as $resp) {
            $certStatus = match ($resp['status']) {
                self::STATUS_GOOD => $this->asn1Implicit(0, ''), // [0] IMPLICIT NULL
                self::STATUS_REVOKED => $this->asn1Implicit(1, $this->buildRevokedInfo($resp)),
                default => $this->asn1Implicit(2, ''), // [2] IMPLICIT NULL (unknown)
            };

            // CertID (simplified - just serial)
            $certId = $this->asn1Sequence(
                $this->asn1Sequence( // hashAlgorithm (SHA-256)
                    $this->asn1Oid('2.16.840.1.101.3.4.2.1') . $this->asn1Null()
                ) .
                $this->asn1OctetString(str_repeat("\x00", 32)) . // issuerNameHash placeholder
                $this->asn1OctetString(str_repeat("\x00", 32)) . // issuerKeyHash placeholder
                $this->asn1Integer(hex2bin($resp['serial']))
            );

            // thisUpdate (GeneralizedTime)
            $thisUpdate = $this->asn1GeneralizedTime(gmdate('YmdHis') . 'Z');

            $singleResponses .= $this->asn1Sequence($certId . $certStatus . $thisUpdate);
        }

        // ResponderID (byName)
        $responderId = $this->asn1Explicit(1, $this->asn1Sequence(
            $this->asn1Set($this->asn1Sequence(
                $this->asn1Oid('2.5.4.3') . $this->asn1Utf8String('DocuTrust OCSP Responder')
            ))
        ));

        // producedAt
        $producedAt = $this->asn1GeneralizedTime(gmdate('YmdHis') . 'Z');

        $tbsResponseData = $this->asn1Sequence(
            $responderId . $producedAt . $this->asn1Sequence($singleResponses)
        );

        // Add nonce extension if present in request
        if ($request['nonce'] !== null) {
            $nonceExt = $this->asn1Sequence(
                $this->asn1Oid('1.3.6.1.5.5.7.48.1.2') . // id-pkix-ocsp-nonce
                $this->asn1OctetString($request['nonce'])
            );
            $tbsResponseData .= $this->asn1Explicit(1, $this->asn1Sequence($nonceExt));
        }

        return $tbsResponseData;
    }

    /**
     * Sign the response data with CA private key.
     *
     * @param string $responseData
     * @param CertificateAuthority $ca
     * @return string
     */
    private function signResponse(string $responseData, CertificateAuthority $ca): string
    {
        $privateKey = openssl_pkey_get_private($ca->private_key_pem);
        if ($privateKey === false) {
            throw new RuntimeException('Unable to load CA private key for OCSP signing.');
        }

        $signature = '';
        if (!openssl_sign($responseData, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Unable to sign OCSP response.');
        }

        return $signature;
    }

    /**
     * Wrap response in OCSPResponse structure.
     *
     * @param string $responseData
     * @param string $signature
     * @param CertificateAuthority $ca
     * @return string
     */
    private function wrapOcspResponse(string $responseData, string $signature, CertificateAuthority $ca): string
    {
        // signatureAlgorithm (SHA-256 with RSA)
        $signatureAlgorithm = $this->asn1Sequence(
            $this->asn1Oid('1.2.840.113549.1.1.11') . $this->asn1Null()
        );

        // BasicOCSPResponse
        $basicResponse = $this->asn1Sequence(
            $responseData .
            $signatureAlgorithm .
            $this->asn1BitString($signature)
        );

        // ResponseBytes
        $responseBytes = $this->asn1Sequence(
            $this->asn1Oid('1.3.6.1.5.5.7.48.1.1') . // id-pkix-ocsp-basic
            $this->asn1OctetString($basicResponse)
        );

        // OCSPResponse (successful)
        return $this->asn1Sequence(
            $this->asn1Enumerated(0) . // responseStatus: successful
            $this->asn1Explicit(0, $responseBytes)
        );
    }

    private function buildMalformedResponse(): string
    {
        return $this->asn1Sequence($this->asn1Enumerated(1)); // malformedRequest
    }

    private function buildInternalErrorResponse(): string
    {
        return $this->asn1Sequence($this->asn1Enumerated(2)); // internalError
    }

    private function buildRevokedInfo(array $resp): string
    {
        $time = $resp['revoked_at'] ?? gmdate('YmdHis') . 'Z';
        return $this->asn1GeneralizedTime($time);
    }

    private function extractNonce(string $der): ?string
    {
        // Search for nonce OID (1.3.6.1.5.5.7.48.1.2)
        $nonceOid = hex2bin('06092b0601050507300102');
        $pos = strpos($der, $nonceOid);
        if ($pos === false) {
            return null;
        }

        // Extract nonce value after OID
        $offset = $pos + strlen($nonceOid);
        if ($offset + 2 < strlen($der) && ord($der[$offset]) === 0x04) {
            $len = ord($der[$offset + 1]);
            return substr($der, $offset + 2, $len);
        }

        return null;
    }

    private function mapRevocationReason(?string $reason): int
    {
        return match ($reason) {
            'keyCompromise' => self::REASON_KEY_COMPROMISE,
            'caCompromise' => self::REASON_CA_COMPROMISE,
            'affiliationChanged' => self::REASON_AFFILIATION_CHANGED,
            'superseded' => self::REASON_SUPERSEDED,
            'cessationOfOperation' => self::REASON_CESSATION_OF_OPERATION,
            'certificateHold' => self::REASON_CERTIFICATE_HOLD,
            default => self::REASON_UNSPECIFIED,
        };
    }

    // ASN.1 DER encoding helpers

    private function parseAsn1Length(string $data, int &$offset): ?int
    {
        if ($offset >= strlen($data)) {
            return null;
        }

        $byte = ord($data[$offset]);
        $offset++;

        if ($byte < 0x80) {
            return $byte;
        }

        $numBytes = $byte & 0x7F;
        if ($numBytes === 0 || $offset + $numBytes > strlen($data)) {
            return null;
        }

        $length = 0;
        for ($i = 0; $i < $numBytes; $i++) {
            $length = ($length << 8) | ord($data[$offset]);
            $offset++;
        }

        return $length;
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
        // Ensure positive integer (prepend 0x00 if high bit set)
        if (strlen($value) > 0 && (ord($value[0]) & 0x80)) {
            $value = "\x00" . $value;
        }
        return "\x02" . $this->asn1Length(strlen($value)) . $value;
    }

    private function asn1Enumerated(int $value): string
    {
        return "\x0A\x01" . chr($value);
    }

    private function asn1OctetString(string $value): string
    {
        return "\x04" . $this->asn1Length(strlen($value)) . $value;
    }

    private function asn1BitString(string $value): string
    {
        $content = "\x00" . $value; // 0 unused bits
        return "\x03" . $this->asn1Length(strlen($content)) . $content;
    }

    private function asn1Null(): string
    {
        return "\x05\x00";
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
                $bytes = '';
                $temp = $value;
                $bytes = chr($temp & 0x7F);
                $temp >>= 7;
                while ($temp > 0) {
                    $bytes = chr(0x80 | ($temp & 0x7F)) . $bytes;
                    $temp >>= 7;
                }
                $encoded .= $bytes;
            }
        }

        return "\x06" . $this->asn1Length(strlen($encoded)) . $encoded;
    }

    private function asn1Utf8String(string $value): string
    {
        return "\x0C" . $this->asn1Length(strlen($value)) . $value;
    }

    private function asn1GeneralizedTime(string $time): string
    {
        return "\x18" . $this->asn1Length(strlen($time)) . $time;
    }

    private function asn1Explicit(int $tag, string $content): string
    {
        $tagByte = chr(0xA0 | $tag);
        return $tagByte . $this->asn1Length(strlen($content)) . $content;
    }

    private function asn1Implicit(int $tag, string $content): string
    {
        $tagByte = chr(0x80 | $tag);
        return $tagByte . $this->asn1Length(strlen($content)) . $content;
    }
}
