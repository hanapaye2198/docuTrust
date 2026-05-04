<?php

use App\Enums\UserRole;
use App\Models\SignerCertificate;
use App\Services\SignerCertificateRevocationService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    /** @var array<int, string> */
    public array $revocationReasons = [];

    public function revokeCertificate(int $certificateId): void
    {
        abort_unless(Auth::user()?->role === UserRole::Notary, 403);

        $certificate = SignerCertificate::query()
            ->whereKey($certificateId)
            ->whereHas('documentSigner.document', fn ($query) => $query->where('organization_id', Auth::user()?->organization_id))
            ->firstOrFail();

        $reason = trim((string) ($this->revocationReasons[$certificateId] ?? ''));

        validator(
            ['reason' => $reason],
            ['reason' => ['required', 'string', 'max:255']]
        )->validate();

        app(SignerCertificateRevocationService::class)->revoke($certificate, $reason);

        $this->revocationReasons[$certificateId] = '';
        session()->flash('status', __('Signer certificate revoked.'));
    }

    /**
     * @return array{
     *   active: Collection<int, SignerCertificate>,
     *   revoked: Collection<int, SignerCertificate>,
     *   total_active: int,
     *   total_revoked: int
     * }
     */
    public function with(): array
    {
        $organizationId = Auth::user()?->organization_id;

        $query = SignerCertificate::query()
            ->with(['documentSigner.document'])
            ->whereHas('documentSigner.document', fn ($builder) => $builder->where('organization_id', $organizationId))
            ->latest();

        $activeCertificates = (clone $query)
            ->where('status', 'active')
            ->whereNull('revoked_at')
            ->limit(8)
            ->get();

        $revokedCertificates = (clone $query)
            ->where(function ($builder): void {
                $builder->where('status', 'revoked')->orWhereNotNull('revoked_at');
            })
            ->limit(8)
            ->get();

        return [
            'activeCertificates' => $activeCertificates,
            'revokedCertificates' => $revokedCertificates,
            'totalActiveCertificates' => (clone $query)
                ->where('status', 'active')
                ->whereNull('revoked_at')
                ->count(),
            'totalRevokedCertificates' => (clone $query)
                ->where(function ($builder): void {
                    $builder->where('status', 'revoked')->orWhereNotNull('revoked_at');
                })
                ->count(),
        ];
    }
}; ?>

<div class="mx-auto flex w-full max-w-4xl flex-col gap-6">
    <header>
        <h1 class="text-2xl font-semibold tracking-tight text-zinc-900 dark:text-zinc-50 sm:text-3xl">{{ __('Notary workspace') }}</h1>
        <p class="mt-2 text-zinc-600 dark:text-zinc-400">
            {{ __('Manage signer certificate status for your organization and revoke compromised credentials when trust is at risk.') }}
        </p>
    </header>

    @if (session('status'))
        <div class="rounded-2xl border border-emerald-200/90 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900/50 dark:bg-emerald-950/30 dark:text-emerald-100">
            {{ session('status') }}
        </div>
    @endif

    <div class="grid gap-4 sm:grid-cols-2">
        <div class="rounded-2xl border border-emerald-200/80 bg-emerald-50 p-5 shadow-sm dark:border-emerald-900/40 dark:bg-emerald-950/20">
            <div class="text-xs font-semibold uppercase tracking-wider text-emerald-700 dark:text-emerald-400">{{ __('Active certificates') }}</div>
            <div class="mt-3 text-3xl font-bold tracking-tight text-emerald-700 dark:text-emerald-300">{{ $totalActiveCertificates }}</div>
        </div>
        <div class="rounded-2xl border border-red-200/80 bg-red-50 p-5 shadow-sm dark:border-red-900/40 dark:bg-red-950/20">
            <div class="text-xs font-semibold uppercase tracking-wider text-red-700 dark:text-red-400">{{ __('Revoked certificates') }}</div>
            <div class="mt-3 text-3xl font-bold tracking-tight text-red-700 dark:text-red-300">{{ $totalRevokedCertificates }}</div>
        </div>
    </div>

    <div class="ui-panel p-6 sm:p-8">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h2 class="text-sm font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('Active signer certificates') }}</h2>
                <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">{{ __('Revoke certificates that should no longer be trusted.') }}</p>
            </div>
        </div>

        <div class="mt-4 space-y-4">
            @forelse ($activeCertificates as $certificate)
                <div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div class="min-w-0 flex-1 space-y-1 text-sm text-zinc-600 dark:text-zinc-300">
                            <div class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ $certificate->documentSigner?->name ?? __('Unknown signer') }}</div>
                            <div><span class="font-medium">{{ __('Document:') }}</span> {{ $certificate->documentSigner?->document?->title ?? '-' }}</div>
                            <div><span class="font-medium">{{ __('Serial:') }}</span> <span class="break-all">{{ $certificate->serial_number }}</span></div>
                            <div><span class="font-medium">{{ __('Fingerprint:') }}</span> <span class="break-all">{{ $certificate->fingerprint_sha256 }}</span></div>
                            <div><span class="font-medium">{{ __('Valid until:') }}</span> {{ $certificate->valid_to?->toDateTimeString() ?? '-' }}</div>
                        </div>

                        <div class="w-full max-w-sm space-y-3">
                            <flux:field>
                                <flux:label>{{ __('Revocation reason') }}</flux:label>
                                <flux:input wire:model="revocationReasons.{{ $certificate->id }}" type="text" placeholder="{{ __('e.g. Identity mismatch') }}" />
                            </flux:field>
                            <flux:button type="button" variant="outline" wire:click="revokeCertificate({{ $certificate->id }})">
                                {{ __('Revoke certificate') }}
                            </flux:button>
                        </div>
                    </div>
                </div>
            @empty
                <div class="rounded-xl border border-dashed border-zinc-300 px-4 py-6 text-sm text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
                    {{ __('No active signer certificates are waiting for review.') }}
                </div>
            @endforelse
        </div>
    </div>

    <div class="ui-panel p-6 sm:p-8">
        <h2 class="text-sm font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('Recent revocations') }}</h2>
        <div class="mt-4 space-y-4">
            @forelse ($revokedCertificates as $certificate)
                <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm dark:border-red-900/40 dark:bg-red-950/20">
                    <div class="font-semibold text-red-800 dark:text-red-200">{{ $certificate->documentSigner?->name ?? __('Unknown signer') }}</div>
                    <div class="mt-1 text-red-700 dark:text-red-300">{{ $certificate->documentSigner?->document?->title ?? '-' }}</div>
                    <div class="mt-2 break-all text-red-700 dark:text-red-300">{{ __('Reason:') }} {{ $certificate->revocation_reason ?? '-' }}</div>
                    <div class="mt-1 text-red-700 dark:text-red-300">{{ __('Revoked at:') }} {{ $certificate->revoked_at?->toDateTimeString() ?? '-' }}</div>
                </div>
            @empty
                <div class="rounded-xl border border-dashed border-zinc-300 px-4 py-6 text-sm text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
                    {{ __('No certificate revocations recorded yet.') }}
                </div>
            @endforelse
        </div>
    </div>
</div>
