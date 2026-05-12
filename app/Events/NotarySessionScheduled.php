<?php

namespace App\Events;

use App\Models\NotaryRequest;
use App\Models\NotarySession;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NotarySessionScheduled
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly NotaryRequest $notaryRequest,
        public readonly NotarySession $notarySession,
    ) {}
}
