<?php

namespace App\Services;

use App\Models\Document;
use App\Models\SignatureAuditEvent;
use App\Models\DocumentSigner;
use Illuminate\Support\Facades\Log;

final class SignatureAuditLogger
{
    public static function fieldPlaced(Document $document, int $signerId, string $ipAddress): void
    {
        SignatureAuditEvent::query()->create([
            'document_id' => $document->id,
            'signer_id' => $signerId,
            'action' => SignatureAuditEvent::ACTION_PLACED,
            'ip_address' => $ipAddress,
        ]);

        Log::channel('audit')->info('Signature field placed', [
            'document_id' => $document->id,
            'signer_id' => $signerId,
            'ip_address' => $ipAddress,
        ]);
    }

    public static function fieldSigned(Document $document, DocumentSigner $signer, string $ipAddress): void
    {
        SignatureAuditEvent::query()->create([
            'document_id' => $document->id,
            'signer_id' => $signer->id,
            'action' => SignatureAuditEvent::ACTION_SIGNED,
            'ip_address' => $ipAddress,
        ]);

        Log::channel('audit')->info('Signature field signed', [
            'document_id' => $document->id,
            'signer_id' => $signer->id,
            'ip_address' => $ipAddress,
        ]);
    }

    public static function documentCompleted(Document $document, string $ipAddress): void
    {
        SignatureAuditEvent::query()->create([
            'document_id' => $document->id,
            'signer_id' => null,
            'action' => SignatureAuditEvent::ACTION_COMPLETED,
            'ip_address' => $ipAddress,
        ]);

        Log::channel('audit')->info('Document completed', [
            'document_id' => $document->id,
            'ip_address' => $ipAddress,
        ]);
    }
}
