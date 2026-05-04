<?php

use App\Models\DocumentHash;
use App\Models\SignatureAuditEvent;
use App\Services\CertificateVerificationService;
use App\Services\DocumentHashService;
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
     *   blockchain_verification: array{
     *     status: 'verified'|'failed'|'not_available',
     *     anchored: bool,
     *     transaction_id: string|null,
     *     transaction_matches: bool|null,
     *     block_number: int|null,
     *     anchored_at: string|null,
     *     submitted_by: string|null,
     *     message: string
     *   },
     *   certificate_verification: array{
     *     status: 'verified'|'failed'|'not_available',
     *     all_valid: bool,
     *     verified_signatures: int,
     *     failed_signatures: int,
     *     message: string,
     *     details: array<int, array{
     *       signer_name: string,
     *       result: 'verified'|'failed',
     *       reason: string,
     *       certificate_status: string|null,
     *       certificate_fingerprint: string|null,
     *       issuer_dn: string|null,
     *       serial_number: string|null,
     *       revoked_at: string|null,
     *       revocation_reason: string|null,
     *       valid_from: string|null,
     *       valid_to: string|null
     *     }>
     *   },
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
                'document.signatures.signerCertificate',
                'document.signatures.signer',
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
        $blockchainVerification = app(DocumentHashService::class)
            ->verifyStoredProof($documentHash);
        $certificateVerification = app(CertificateVerificationService::class)
            ->verifyDocumentSignatures($document, $documentHash->hash);

        $this->verificationResult = [
            'hash' => $documentHash->hash,
            'document_id' => $documentHash->document_id,
            'status' => $document->status->value,
            'completed_at' => $completedAt,
            'blockchain_verification' => $blockchainVerification,
            'certificate_verification' => $certificateVerification,
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
                <flux:input wire:model="documentIdentifier" type="text" placeholder="{{ __('e.g. 12 or abc123...') }}" autocomplete="off" />
                <flux:error name="documentIdentifier" />
            </flux:field>
            <flux:button variant="primary" type="submit" class="w-full sm:w-auto">{{ __('Verify now') }}</flux:button>
        </form>
    </div>

    @if ($hasAttemptedVerification && $verificationResult === null)
        <div class="rounded-2xl border border-red-300/80 bg-red-50 p-6 text-red-800 dark:border-red-700/80 dark:bg-red-950/30 dark:text-red-100">
            <div class="text-lg font-semibold">{{ __('Invalid') }}</div>
            <p class="mt-2 text-sm">{{ __('Invalid or unverified document') }}</p>
        </div>
    @endif

    @if ($verificationResult !== null)
        <div class="space-y-6 rounded-2xl border border-emerald-300/80 bg-emerald-50 p-6 dark:border-emerald-700/80 dark:bg-emerald-950/20">
            <div>
                <div class="text-lg font-semibold text-emerald-800 dark:text-emerald-100">{{ __('Valid') }}</div>
                <p class="mt-1 text-sm text-emerald-700 dark:text-emerald-200">{{ __('Document verification successful.') }}</p>
            </div>

            <div class="grid gap-3 text-sm text-zinc-800 dark:text-zinc-200">
                <div><span class="font-semibold">{{ __('Hash:') }}</span> <span class="break-all">{{ $verificationResult['hash'] }}</span></div>
                <div><span class="font-semibold">{{ __('Document ID:') }}</span> {{ $verificationResult['document_id'] }}</div>
                <div><span class="font-semibold">{{ __('Status:') }}</span> {{ ucfirst($verificationResult['status']) }}</div>
                <div><span class="font-semibold">{{ __('Date completed:') }}</span> {{ $verificationResult['completed_at'] ?? '-' }}</div>
            </div>

            <div>
                <h2 class="text-sm font-semibold uppercase tracking-wider text-zinc-600 dark:text-zinc-300">{{ __('Blockchain verification') }}</h2>
                <div class="mt-2 rounded-lg border border-zinc-200/80 bg-white/80 px-3 py-3 text-sm dark:border-zinc-700 dark:bg-zinc-900/70">
                    <div class="font-medium">
                        @if ($verificationResult['blockchain_verification']['status'] === 'verified')
                            {{ __('Verified') }}
                        @elseif ($verificationResult['blockchain_verification']['status'] === 'failed')
                            {{ __('Failed') }}
                        @else
                            {{ __('Not available') }}
                        @endif
                    </div>
                    <div class="mt-1 text-zinc-600 dark:text-zinc-300">{{ $verificationResult['blockchain_verification']['message'] }}</div>
                    @if ($verificationResult['blockchain_verification']['transaction_id'] !== null)
                        <div class="mt-2 break-all text-zinc-700 dark:text-zinc-200">
                            {{ __('Transaction:') }} {{ $verificationResult['blockchain_verification']['transaction_id'] }}
                        </div>
                    @endif
                    @if ($verificationResult['blockchain_verification']['block_number'] !== null)
                        <div class="mt-1 text-zinc-700 dark:text-zinc-200">
                            {{ __('Block number:') }} {{ $verificationResult['blockchain_verification']['block_number'] }}
                        </div>
                    @endif
                    @if ($verificationResult['blockchain_verification']['anchored_at'] !== null)
                        <div class="mt-1 text-zinc-700 dark:text-zinc-200">
                            {{ __('Anchored at:') }} {{ $verificationResult['blockchain_verification']['anchored_at'] }}
                        </div>
                    @endif
                    @if ($verificationResult['blockchain_verification']['submitted_by'] !== null)
                        <div class="mt-1 break-all text-zinc-700 dark:text-zinc-200">
                            {{ __('Submitted by:') }} {{ $verificationResult['blockchain_verification']['submitted_by'] }}
                        </div>
                    @endif
                </div>
            </div>

            <div>
                <h2 class="text-sm font-semibold uppercase tracking-wider text-zinc-600 dark:text-zinc-300">{{ __('Certificate verification') }}</h2>
                <div class="mt-2 rounded-lg border border-zinc-200/80 bg-white/80 px-3 py-3 text-sm dark:border-zinc-700 dark:bg-zinc-900/70">
                    <div class="font-medium">
                        @if ($verificationResult['certificate_verification']['status'] === 'verified')
                            {{ __('Verified') }}
                        @elseif ($verificationResult['certificate_verification']['status'] === 'failed')
                            {{ __('Failed') }}
                        @else
                            {{ __('Not available') }}
                        @endif
                    </div>
                    <div class="mt-1 text-zinc-600 dark:text-zinc-300">{{ $verificationResult['certificate_verification']['message'] }}</div>
                    @if ($verificationResult['certificate_verification']['status'] !== 'not_available')
                        <div class="mt-2 text-zinc-700 dark:text-zinc-200">
                            {{ __('Verified signatures:') }} {{ $verificationResult['certificate_verification']['verified_signatures'] }}
                            <span class="mx-2 text-zinc-400">•</span>
                            {{ __('Failed signatures:') }} {{ $verificationResult['certificate_verification']['failed_signatures'] }}
                        </div>
                    @endif
                </div>

                @if ($verificationResult['certificate_verification']['details'] !== [])
                    <div class="mt-2 space-y-2">
                        @foreach ($verificationResult['certificate_verification']['details'] as $certificateDetail)
                            <div class="rounded-lg border border-zinc-200/80 bg-white/80 px-3 py-3 text-sm dark:border-zinc-700 dark:bg-zinc-900/70">
                                <div class="font-medium">{{ $certificateDetail['signer_name'] }}</div>
                                <div class="mt-1">{{ ucfirst($certificateDetail['result']) }} <span class="mx-2 text-zinc-400">•</span> {{ $certificateDetail['reason'] }}</div>
                                @if ($certificateDetail['serial_number'] !== null)
                                    <div class="mt-2 break-all text-zinc-600 dark:text-zinc-300">{{ __('Serial:') }} {{ $certificateDetail['serial_number'] }}</div>
                                @endif
                                @if ($certificateDetail['certificate_status'] !== null)
                                    <div class="mt-1 break-all text-zinc-600 dark:text-zinc-300">{{ __('Certificate status:') }} {{ ucfirst($certificateDetail['certificate_status']) }}</div>
                                @endif
                                @if ($certificateDetail['revoked_at'] !== null)
                                    <div class="mt-1 break-all text-zinc-600 dark:text-zinc-300">{{ __('Revoked at:') }} {{ $certificateDetail['revoked_at'] }}</div>
                                @endif
                                @if ($certificateDetail['revocation_reason'] !== null)
                                    <div class="mt-1 break-all text-zinc-600 dark:text-zinc-300">{{ __('Revocation reason:') }} {{ $certificateDetail['revocation_reason'] }}</div>
                                @endif
                                @if ($certificateDetail['issuer_dn'] !== null)
                                    <div class="mt-1 break-all text-zinc-600 dark:text-zinc-300">{{ __('Issuer:') }} {{ $certificateDetail['issuer_dn'] }}</div>
                                @endif
                                @if ($certificateDetail['certificate_fingerprint'] !== null)
                                    <div class="mt-1 break-all text-zinc-600 dark:text-zinc-300">{{ __('Fingerprint:') }} {{ $certificateDetail['certificate_fingerprint'] }}</div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
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
                            <span>{{ $signer['signed_at'] ?? '-' }}</span>
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
                            <span>{{ $timelineItem['occurred_at'] ?? '-' }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif
</div>
