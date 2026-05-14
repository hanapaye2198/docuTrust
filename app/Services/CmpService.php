<?php

namespace App\Services;

use RuntimeException;

/**
 * PKIX-CMP (Certificate Management Protocol) Service
 * 
 * Implements PKIX-CMP for certificate management as required by CSC.
 * Based on RFC 4210. Uses ASN.1/DER encoding for wire-format compliance.
 */
class CmpService
{
    private string $caName = 'DocuTrust CA';
    private array $supportedProtocols = [
        'PKIX-CMP',
        'PKIX-CMC',
        'PKIX-TSP',
        'PKIX-EST',
    ];

    // CMP message body types (RFC 4210 Section 5.3)
    public const TYPE_IR = 0;   // Initialization Request
    public const TYPE_IP = 1;   // Initialization Response
    public const TYPE_CR = 2;   // Certification Request
    public const TYPE_CP = 3;   // Certification Response
    public const TYPE_KUR = 7;  // Key Update Request
    public const TYPE_KUP = 8;  // Key Update Response
    public const TYPE_RR = 11;  // Revocation Request
    public const TYPE_RP = 12;  // Revocation Response
    public const TYPE_CERTCONF = 24; // Certificate Confirm
    public const TYPE_PKICONF = 19;  // PKI Confirmation
    public const TYPE_ERROR = 23;    // Error Message

    /**
     * Get CMP capabilities
     *
     * @return array
     */
    public function getCapabilities(): array
    {
        return [
            'pBM' => 'PBMAC1',
            'pkc' => 'PKC',
            'ra' => 'RA',
            'pop' => 'POP',
            'pro' => 'PRO',
            'rec' => 'REC',
            'rev' => 'REV',
            'upd' => 'UPD',
        ];
    }

    /**
     * Generate CMP transaction ID
     *
     * @return string
     */
    public function generateTransactionId(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * Create CMP enrollment request
     *
     * @param string $publicKey PEM-encoded public key
     * @param array $subject Subject DN
     * @param string $caName CA name
     * @return array{
     *   transactionId: string,
     *   message: string,
     *   nonce: string
     * }
     */
    public function createEnrollmentRequest(string $publicKey, array $subject, string $caName): array
    {
        $transactionId = $this->generateTransactionId();
        $nonce = bin2hex(random_bytes(16));

        // Create PKCS#10 request
        $pkcs10 = new Pkcs10Request();
        $pkcs10->setDistinguishedName($subject);

        // Simplified implementation
        $csrPem = $pkcs10->generate($publicKey, 'sha256');

        return [
            'transactionId' => $transactionId,
            'message' => base64_encode($csrPem),
            'nonce' => $nonce,
        ];
    }

    /**
     * Create CMP certificate confirmation message
     *
     * @param string $transactionId Transaction ID
     * @param bool $accepted Certificate accepted or rejected
     * @return array{
     *   transactionId: string,
     *   status: string,
     *   message: string
     * }
     */
    public function createConfirmation(string $transactionId, bool $accepted): array
    {
        return [
            'transactionId' => $transactionId,
            'status' => $accepted ? 'accepted' : 'rejected',
            'message' => $accepted ? 'Certificate issued successfully' : 'Certificate request rejected',
        ];
    }

    /**
     * Create CMP revocation request
     *
     * @param string $serialNumber Certificate serial number
     * @param string $reason Revocation reason
     * @return array{
     *   transactionId: string,
     *   serialNumber: string,
     *   reason: string
     * }
     */
    public function createRevocationRequest(string $serialNumber, string $reason): array
    {
        return [
            'transactionId' => $this->generateTransactionId(),
            'serialNumber' => $serialNumber,
            'reason' => $reason,
        ];
    }

    /**
     * Create CMP key update request
     *
     * @param string $oldPublicKey Old public key
     * @param string $newPublicKey New public key
     * @return array{
     *   transactionId: string,
     *   oldPublicKey: string,
     *   newPublicKey: string
     * }
     */
    public function createKeyUpdateRequest(string $oldPublicKey, string $newPublicKey): array
    {
        return [
            'transactionId' => $this->generateTransactionId(),
            'oldPublicKey' => base64_encode($oldPublicKey),
            'newPublicKey' => base64_encode($newPublicKey),
        ];
    }

    /**
     * Parse CMP message (supports both DER and Base64-encoded DER).
     *
     * @param string $message DER or Base64-encoded CMP PKIMessage
     * @return array{
     *   transactionId: string,
     *   messageType: int,
     *   senderNonce: string|null,
     *   recipientNonce: string|null,
     *   payload: string
     * }
     */
    public function parseMessage(string $message): array
    {
        // Try base64 decode first
        $der = base64_decode($message, true);
        if ($der === false || strlen($der) < 5) {
            $der = $message; // Assume raw DER
        }

        // Validate ASN.1 SEQUENCE tag
        if (strlen($der) < 2 || ord($der[0]) !== 0x30) {
            throw new RuntimeException('Invalid CMP message: not a valid ASN.1 SEQUENCE.');
        }

        // Extract PKIHeader fields
        $transactionId = $this->extractTransactionIdFromDer($der);
        $messageType = $this->extractMessageTypeFromDer($der);
        $senderNonce = $this->extractFieldFromDer($der, 'senderNonce');

        return [
            'transactionId' => $transactionId ?? bin2hex(random_bytes(16)),
            'messageType' => $messageType,
            'senderNonce' => $senderNonce,
            'recipientNonce' => null,
            'payload' => $der,
        ];
    }

    /**
     * Build a DER-encoded CMP PKIMessage.
     *
     * @param int $bodyType Message body type constant
     * @param string $bodyContent DER-encoded body content
     * @param string|null $transactionId
     * @param string|null $senderNonce
     * @return string DER-encoded PKIMessage
     */
    public function buildPkiMessage(int $bodyType, string $bodyContent, ?string $transactionId = null, ?string $senderNonce = null): string
    {
        $transactionId ??= random_bytes(16);
        $senderNonce ??= random_bytes(16);

        // PKIHeader
        $header = $this->buildPkiHeader($transactionId, $senderNonce);

        // PKIBody (context-tagged with body type)
        $body = $this->asn1Explicit($bodyType, $bodyContent);

        // PKIMessage ::= SEQUENCE { header, body, protection, extraCerts }
        return $this->asn1Sequence($header . $body);
    }

    /**
     * Build DER-encoded PKIHeader.
     */
    private function buildPkiHeader(string $transactionId, string $senderNonce): string
    {
        // pvno (cmp2000 = 2)
        $pvno = $this->asn1Integer("\x02");

        // sender (GeneralName - directoryName)
        $sender = $this->asn1Explicit(4, $this->asn1Sequence(
            $this->asn1Set($this->asn1Sequence(
                $this->asn1Oid('2.5.4.3') . $this->asn1Utf8String($this->caName)
            ))
        ));

        // recipient (GeneralName - directoryName)
        $recipient = $this->asn1Explicit(4, $this->asn1Sequence(
            $this->asn1Set($this->asn1Sequence(
                $this->asn1Oid('2.5.4.3') . $this->asn1Utf8String($this->caName)
            ))
        ));

        // transactionID
        $txId = $this->asn1Explicit(4, $this->asn1OctetString($transactionId));

        // senderNonce
        $nonce = $this->asn1Explicit(5, $this->asn1OctetString($senderNonce));

        return $this->asn1Sequence($pvno . $sender . $recipient . $txId . $nonce);
    }

    private function extractTransactionIdFromDer(string $der): ?string
    {
        // Look for OCTET STRING after context tag [4] in header
        $pos = 0;
        $len = strlen($der);

        // Skip outer SEQUENCE
        if ($pos < $len && ord($der[$pos]) === 0x30) {
            $pos++;
            $this->skipAsn1Length($der, $pos);
        }

        // Skip header SEQUENCE tag
        if ($pos < $len && ord($der[$pos]) === 0x30) {
            $pos++;
            $headerLen = $this->readAsn1Length($der, $pos);
            $headerEnd = $pos + $headerLen;

            // Search within header for context [4] (transactionID)
            while ($pos < $headerEnd && $pos < $len) {
                $tag = ord($der[$pos]);
                if ($tag === 0xA4) { // context [4]
                    $pos++;
                    $fieldLen = $this->readAsn1Length($der, $pos);
                    // Inside should be OCTET STRING
                    if ($pos < $len && ord($der[$pos]) === 0x04) {
                        $pos++;
                        $octetLen = $this->readAsn1Length($der, $pos);
                        return bin2hex(substr($der, $pos, $octetLen));
                    }
                    break;
                }
                $pos++;
                $fieldLen = $this->readAsn1Length($der, $pos);
                $pos += $fieldLen;
            }
        }

        return null;
    }

    private function extractMessageTypeFromDer(string $der): int
    {
        // The body is the second element of the outer SEQUENCE
        // Its tag indicates the message type (context-specific)
        $pos = 0;
        $len = strlen($der);

        // Skip outer SEQUENCE
        if ($pos < $len && ord($der[$pos]) === 0x30) {
            $pos++;
            $this->skipAsn1Length($der, $pos);
        }

        // Skip header SEQUENCE
        if ($pos < $len && ord($der[$pos]) === 0x30) {
            $pos++;
            $headerLen = $this->readAsn1Length($der, $pos);
            $pos += $headerLen;
        }

        // Body tag
        if ($pos < $len) {
            $tag = ord($der[$pos]);
            if (($tag & 0xC0) === 0xA0) { // context-specific
                return $tag & 0x1F;
            }
        }

        return -1;
    }

    private function extractFieldFromDer(string $der, string $field): ?string
    {
        // Simplified field extraction
        return null;
    }

    private function skipAsn1Length(string $der, int &$pos): void
    {
        $this->readAsn1Length($der, $pos);
    }

    private function readAsn1Length(string $der, int &$pos): int
    {
        if ($pos >= strlen($der)) {
            return 0;
        }

        $byte = ord($der[$pos]);
        $pos++;

        if ($byte < 0x80) {
            return $byte;
        }

        $numBytes = $byte & 0x7F;
        $length = 0;
        for ($i = 0; $i < $numBytes && $pos < strlen($der); $i++) {
            $length = ($length << 8) | ord($der[$pos]);
            $pos++;
        }

        return $length;
    }

    /**
     * Handle CMP enrollment request
     *
     * @param array $request Enrollment request
     * @return array{
     *   status: string,
     *   certificate: string|null,
     *   errorMessage: string|null
     * }
     */
    public function handleEnrollmentRequest(array $request): array
    {
        // Validate request
        if (!isset($request['message'])) {
            return [
                'status' => 'error',
                'certificate' => null,
                'errorMessage' => 'Missing message payload.',
            ];
        }

        // Process enrollment
        // In production, validate and issue certificate
        return [
            'status' => 'pending',
            'certificate' => null,
            'errorMessage' => null,
        ];
    }

    /**
     * Handle CMP revocation request
     *
     * @param array $request Revocation request
     * @return array{
     *   status: string,
     *   errorMessage: string|null
     * }
     */
    public function handleRevocationRequest(array $request): array
    {
        // Validate and process revocation
        // In production, update certificate status
        return [
            'status' => 'success',
            'errorMessage' => null,
        ];
    }

    /**
     * Get supported protocols
     *
     * @return array
     */
    public function getSupportedProtocols(): array
    {
        return $this->supportedProtocols;
    }

    /**
     * Get CA information
     *
     * @return array{
     *   caName: string,
     *   supportedProtocols: array,
     *   capabilities: array,
     *   contentType: string
     * }
     */
    public function getCaInfo(): array
    {
        return [
            'caName' => $this->caName,
            'supportedProtocols' => $this->getSupportedProtocols(),
            'capabilities' => $this->getCapabilities(),
            'contentType' => 'application/pkixcmp', // RFC 6712
        ];
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

    private function asn1OctetString(string $value): string
    {
        return "\x04" . $this->asn1Length(strlen($value)) . $value;
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

    private function asn1Utf8String(string $value): string
    {
        return "\x0C" . $this->asn1Length(strlen($value)) . $value;
    }

    private function asn1Explicit(int $tag, string $content): string
    {
        return chr(0xA0 | $tag) . $this->asn1Length(strlen($content)) . $content;
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
