<?php

use App\Models\DocumentHash;
use App\Models\SignatureAuditEvent;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.guest-simple')] class extends Component {
    public string $documentIdentifier = '';

    /**
     * @var array{
     *   hash: string,
     *   document_id: int,
     *   status: string,
     *   completed_at: string|null,
     *   signers: array<int, array{name: string, status: string, signed_at: string|null}>,
     *   timeline: array<int, array{action: string, actor: string, occurred_at: string|null}>
     * }|null
     */
    public ?array $verificationResult = null;

    public bool $hasAttemptedVerification = false;

    public function mount(): void
    {
        $identifier = trim((string) request()->query('documentIdentifier', ''));

        if ($identifier === '') {
            return;
        }

        $this->documentIdentifier = $identifier;
        $this->verifyNow();
    }

    public function verifyNow(): void
    {
        $this->validate([
            'documentIdentifier' => ['required', 'string', 'max:255'],
        ]);

        $identifier = trim($this->documentIdentifier);

        $documentHash = DocumentHash::query()
            ->with([
                'document.documentSigners' => fn ($query) => $query->orderBy('signing_order')->orderBy('id'),
                'document.signatureAuditEvents' => fn ($query) => $query->orderBy('created_at'),
                'document.signatureAuditEvents.signer',
            ])
            ->when(
                ctype_digit($identifier),
                fn ($query) => $query->where('document_id', (int) $identifier),
                fn ($query) => $query->where('hash', $identifier)
            )
            ->first();

        $this->hasAttemptedVerification = true;
        $this->verificationResult = null;

        if ($documentHash === null) {
            return;
        }

        $document = $documentHash->document;
        $completedAt = $document->signatureAuditEvents
            ->firstWhere('action', SignatureAuditEvent::ACTION_COMPLETED)?->created_at?->toDateTimeString() ?? $documentHash->created_at?->toDateTimeString();

        $this->verificationResult = [
            'hash' => $documentHash->hash,
            'document_id' => $documentHash->document_id,
            'status' => $document->status->value,
            'completed_at' => $completedAt,
            'signers' => $document->documentSigners
                ->map(fn ($signer) => [
                    'name' => $signer->name,
                    'status' => $signer->status->value,
                    'signed_at' => $signer->signed_at?->toDateTimeString(),
                ])
                ->all(),
            'timeline' => $document->signatureAuditEvents
                ->map(fn ($event) => [
                    'action' => $event->action,
                    'actor' => $event->signer?->name ?? __('System'),
                    'occurred_at' => $event->created_at?->toDateTimeString(),
                ])
                ->all(),
        ];
    }
}; ?>

<div class="mx-auto flex min-h-screen w-full max-w-3xl flex-col gap-8 px-6 py-10">
    <div>
        <h1 class="ui-page-heading">{{ __('Verify document') }}</h1>
        <p class="ui-muted mt-2">{{ __('Enter a document hash or document ID to verify authenticity.') }}</p>
    </div>

    <div class="ui-panel p-8">
        <form wire:submit="verifyNow" class="flex flex-col gap-5">
            <flux:field>
                <flux:label>{{ __('Document ID or hash') }}</flux:label>
                <flux:input wire:model="documentIdentifier" type="text" placeholder="{{ __('e.g. 12 or abc123…') }}" autocomplete="off" />
                <flux:error name="documentIdentifier" />
            </flux:field>
            <flux:button variant="primary" type="submit" class="w-full sm:w-auto">{{ __('Verify now') }}</flux:button>
        </form>
    </div>

    @if ($hasAttemptedVerification && $verificationResult === null)
        <div class="rounded-2xl border border-red-300/80 bg-red-50 p-6 text-red-800 dark:border-red-700/80 dark:bg-red-950/30 dark:text-red-100">
            <div class="text-lg font-semibold">❌ {{ __('Invalid') }}</div>
            <p class="mt-2 text-sm">{{ __('Invalid or unverified document') }}</p>
        </div>
    @endif

    @if ($verificationResult !== null)
        <div class="space-y-6 rounded-2xl border border-emerald-300/80 bg-emerald-50 p-6 dark:border-emerald-700/80 dark:bg-emerald-950/20">
            <div>
                <div class="text-lg font-semibold text-emerald-800 dark:text-emerald-100">✔ {{ __('Valid') }}</div>
                <p class="mt-1 text-sm text-emerald-700 dark:text-emerald-200">{{ __('Document verification successful.') }}</p>
            </div>

            <div class="grid gap-3 text-sm text-zinc-800 dark:text-zinc-200">
                <div><span class="font-semibold">{{ __('Hash:') }}</span> <span class="break-all">{{ $verificationResult['hash'] }}</span></div>
                <div><span class="font-semibold">{{ __('Document ID:') }}</span> {{ $verificationResult['document_id'] }}</div>
                <div><span class="font-semibold">{{ __('Status:') }}</span> {{ ucfirst($verificationResult['status']) }}</div>
                <div><span class="font-semibold">{{ __('Date completed:') }}</span> {{ $verificationResult['completed_at'] ?? '—' }}</div>
            </div>

            <div>
                <h2 class="text-sm font-semibold uppercase tracking-wider text-zinc-600 dark:text-zinc-300">{{ __('Signer list') }}</h2>
                <div class="mt-2 space-y-2">
                    @foreach ($verificationResult['signers'] as $signer)
                        <div class="rounded-lg border border-zinc-200/80 bg-white/80 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-900/70">
                            <span class="font-medium">{{ $signer['name'] }}</span>
                            <span class="mx-2 text-zinc-400">•</span>
                            <span>{{ ucfirst($signer['status']) }}</span>
                            <span class="mx-2 text-zinc-400">•</span>
                            <span>{{ $signer['signed_at'] ?? '—' }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            <div>
                <h2 class="text-sm font-semibold uppercase tracking-wider text-zinc-600 dark:text-zinc-300">{{ __('Signing timeline') }}</h2>
                <div class="mt-2 space-y-2">
                    @foreach ($verificationResult['timeline'] as $timelineItem)
                        <div class="rounded-lg border border-zinc-200/80 bg-white/80 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-900/70">
                            <span class="font-medium">{{ ucfirst($timelineItem['action']) }}</span>
                            <span class="mx-2 text-zinc-400">•</span>
                            <span>{{ $timelineItem['actor'] }}</span>
                            <span class="mx-2 text-zinc-400">•</span>
                            <span>{{ $timelineItem['occurred_at'] ?? '—' }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif
</div>
