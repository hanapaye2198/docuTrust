<?php

namespace App\Events;

use App\Models\NotaryRequest;
use App\Services\NotaryRequestStatusPayloadService;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NotaryRequestStatusUpdated implements ShouldBroadcastNow
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @var array<string, mixed>
     */
    public array $payload;

    public function __construct(
        public readonly int $notaryRequestId,
        ?array $payload = null,
    ) {
        $this->payload = $payload ?? app(NotaryRequestStatusPayloadService::class)
            ->build(NotaryRequest::query()->findOrFail($notaryRequestId));
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('notary-request.'.$this->notaryRequestId);
    }

    public function broadcastAs(): string
    {
        return 'notary.request.status.updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
