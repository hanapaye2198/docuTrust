<?php

namespace App\Events;

use App\Models\Document;
use App\Models\DocumentSigner;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DocumentSignerCompleted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Document $document,
        public DocumentSigner $signer,
    ) {}
}
