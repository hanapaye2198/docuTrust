<?php

namespace App\Livewire\Signature;

use App\Models\TrustAuthorizationSession;
use App\Services\Signature\SadLifecycleService;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class TrustAuthorizationStatus extends Component
{
    public int $documentId;

    public int $signerId;

    public string $authStatus = 'pending';

    public function mount(int $documentId, int $signerId): void
    {
        $this->documentId = $documentId;
        $this->signerId = $signerId;
        $this->checkStatus();
    }

    public function checkStatus(): void
    {
        if (app(SadLifecycleService::class)->isValid($this->documentId, $this->signerId)) {
            $this->authStatus = 'authorized';

            return;
        }

        $hasExpiredSession = TrustAuthorizationSession::query()
            ->where('document_id', $this->documentId)
            ->where('document_signer_id', $this->signerId)
            ->where(function ($query): void {
                $query->where('status', 'expired')
                    ->orWhere('expires_at', '<=', now());
            })
            ->latest('id')
            ->exists();

        $this->authStatus = $hasExpiredSession ? 'expired' : 'pending';
    }

    public function shouldPoll(): bool
    {
        return $this->authStatus === 'pending';
    }

    public function render(): View
    {
        return view('livewire.signature.trust-authorization-status');
    }
}
