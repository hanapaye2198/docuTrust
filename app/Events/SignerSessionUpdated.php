<?php

namespace App\Events;

use App\Models\DocumentSigner;
use App\Services\SignerSessionPayloadService;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SignerSessionUpdated implements ShouldBroadcastNow
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @var array<string, mixed>
     */
    public array $payload;

    public readonly string $signerToken;

    public function __construct(
        DocumentSigner $signer,
        ?array $payload = null,
    ) {
        $this->signerToken = (string) ($signer->access_token ?? $signer->id);
        $this->payload = $payload ?? app(SignerSessionPayloadService::class)->build($signer);
    }

    public function broadcastOn(): Channel
    {
        return new Channel('signer-session.'.$this->signerToken);
    }

    public function broadcastAs(): string
    {
        return 'signer.session.updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
