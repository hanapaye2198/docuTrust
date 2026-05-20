<?php

namespace App\Services\Signature;

use App\Models\Document;
use App\Models\DocumentHash;
use App\Support\SignatureFeatures;

/**
 * Wraps the current PKI sealing path and exposes future PAdES / HSM capability flags.
 */
class FuturePKISignatureDriver extends BasicElectronicSignatureDriver
{
    /**
     * @return array<string, bool>
     */
    public function capabilities(): array
    {
        $base = parent::capabilities();
        $base['x509_embed'] = true;
        $base['pades'] = (bool) config('signature.features.pades.enabled', false);
        $base['hsm'] = SignatureFeatures::hsmEnabled();
        $base['tsa'] = (bool) config('services.remote_signing.csc.timestamp_enabled', false);

        return $base;
    }

    public function sealDocument(Document $document): ?DocumentHash
    {
        return parent::sealDocument($document);
    }
}
