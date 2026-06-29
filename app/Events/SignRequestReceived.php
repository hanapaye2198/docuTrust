<?php

namespace App\Events;

use App\Models\DocumentSigner;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SignRequestReceived implements ShouldBroadcastNow
{
    use Dispatchable;
    use SerializesModels;

    public readonly int $userId;

    /**
     * @var array<string, mixed>
     */
    public array $payload;

    public function __construct(DocumentSigner $signer)
    {
        $signer->loadMissing('document.user');

        $this->userId = (int) $signer->user_id;
        $this->payload = [
            'signerId' => $signer->id,
            'documentId' => $signer->document_id,
            'title' => $signer->document?->title ?? __('Document'),
            'sender' => $signer->document?->user?->name ?? __('Someone'),
            'message' => __(':sender requests your signature on ":title".', [
                'sender' => $signer->document?->user?->name ?? __('Someone'),
                'title' => $signer->document?->title ?? __('Document'),
            ]),
            'url' => route('sign-requests.index'),
        ];
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('App.Models.User.'.$this->userId);
    }

    public function broadcastAs(): string
    {
        return 'sign.request.received';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
