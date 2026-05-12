<?php

namespace App\Events;

use App\Models\NotaryRequest;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NotaryRequestNotarized
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly NotaryRequest $notaryRequest,
    ) {}
}
