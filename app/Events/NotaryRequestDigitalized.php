<?php

namespace App\Events;

use App\Models\NotaryRequest;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NotaryRequestDigitalized
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly NotaryRequest $notaryRequest,
    ) {}
}
