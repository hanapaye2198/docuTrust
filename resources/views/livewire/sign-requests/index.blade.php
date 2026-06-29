<?php

use App\Enums\DocumentSignerStatus;
use App\Enums\DocumentStatus;
use App\Enums\SigningMethod;
use App\Models\DocumentSigner;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    public ?string $realtimeNotice = null;

    #[On('sign-request-received')]
    public function showRealtimeNotice(): void
    {
        $this->realtimeNotice = __('New sign request received. Your inbox has been updated.');
    }

    public function with(): array
    {
        $userId = Auth::id();

        $requests = DocumentSigner::query()
            ->with(['document.user'])
            ->where('user_id', $userId)
            ->whereHas('document', fn ($q) => $q->whereIn('status', [
                DocumentStatus::Pending,
                DocumentStatus::Completed,
            ]))
            ->orderByRaw("CASE WHEN status = ? THEN 0 ELSE 1 END", [DocumentSignerStatus::Pending->value])
            ->orderByDesc('id')
            ->get();

        $pendingCount = $requests
            ->filter(fn (DocumentSigner $signer): bool => $signer->status === DocumentSignerStatus::Pending
                && $signer->document?->status === DocumentStatus::Pending)
            ->count();
        $completedCount = $requests
            ->filter(fn (DocumentSigner $signer): bool => in_array($signer->status, [DocumentSignerStatus::Signed, DocumentSignerStatus::Approved], true))
            ->count();
        $approvalCount = $requests
            ->filter(fn (DocumentSigner $signer): bool => $signer->isApprover() && $signer->status === DocumentSignerStatus::Pending)
            ->count();
        $expiringCount = $requests
            ->filter(fn (DocumentSigner $signer): bool => $signer->status === DocumentSignerStatus::Pending
                && $signer->expires_at !== null
                && $signer->expires_at->between(now(), now()->addDays(2)))
            ->count();

        return [
            'requests' => $requests,
            'requestStats' => [
                'total' => $requests->count(),
                'pending' => $pendingCount,
                'completed' => $completedCount,
                'approvals' => $approvalCount,
                'expiring' => $expiringCount,
            ],
        ];
    }
}; ?>

<div class="flex w-full min-w-0 max-w-none flex-col gap-5 px-0 py-4 sm:gap-6 sm:px-2 lg:px-3">

    @if ($realtimeNotice !== null)
        <div class="flex items-start gap-3 rounded-2xl border border-teal-200 bg-teal-50 px-4 py-3 text-sm text-teal-800 shadow-sm dark:border-teal-900/60 dark:bg-teal-950/40 dark:text-teal-200">
            <span class="mt-1 h-2 w-2 shrink-0 rounded-full bg-teal-500"></span>
            <div>
                <p class="font-semibold">{{ $realtimeNotice }}</p>
                <p class="mt-1 text-teal-700/80 dark:text-teal-200/80">{{ __('You can open it below without refreshing this page.') }}</p>
            </div>
        </div>
    @endif

    <div class="overflow-hidden rounded-3xl border border-zinc-200/80 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
        <div class="relative isolate px-4 py-6 sm:px-5 lg:px-6">
            <div class="absolute inset-0 -z-10 bg-[radial-gradient(circle_at_top_left,rgba(20,184,166,0.16),transparent_32rem),linear-gradient(135deg,rgba(240,253,250,0.9),rgba(255,255,255,0))] dark:bg-[radial-gradient(circle_at_top_left,rgba(20,184,166,0.16),transparent_32rem),linear-gradient(135deg,rgba(20,184,166,0.08),rgba(24,24,27,0))]"></div>

            <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                <div class="max-w-2xl">
                    <span class="inline-flex items-center gap-2 rounded-full border border-teal-200 bg-teal-50 px-3 py-1 text-xs font-semibold text-teal-700 dark:border-teal-900/60 dark:bg-teal-950/40 dark:text-teal-300">
                        <span class="h-1.5 w-1.5 rounded-full bg-teal-500"></span>
                        {{ __('Signing inbox') }}
                    </span>
                    <h1 class="mt-4 text-2xl font-bold tracking-tight text-zinc-950 dark:text-zinc-50 sm:text-3xl">
                        {{ __('Sign Requests') }}
                    </h1>
                    <p class="mt-2 text-sm leading-6 text-zinc-600 dark:text-zinc-300">
                        {{ __('Review documents that need your signature or approval, then continue exactly where the sender needs you.') }}
                    </p>
                </div>

                <div class="grid grid-cols-2 gap-2 sm:grid-cols-4 lg:min-w-[380px]">
                    <div class="rounded-2xl border border-white/70 bg-white/80 px-4 py-3 text-center shadow-sm backdrop-blur dark:border-zinc-700/70 dark:bg-zinc-900/70">
                        <p class="text-2xl font-bold text-zinc-950 dark:text-zinc-50">{{ $requestStats['total'] }}</p>
                        <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400">{{ __('Total') }}</p>
                    </div>
                    <div class="rounded-2xl border border-amber-200/80 bg-amber-50/90 px-4 py-3 text-center shadow-sm dark:border-amber-900/50 dark:bg-amber-950/30">
                        <p class="text-2xl font-bold text-amber-700 dark:text-amber-300">{{ $requestStats['pending'] }}</p>
                        <p class="text-xs font-medium text-amber-700/80 dark:text-amber-300/80">{{ __('Pending') }}</p>
                    </div>
                    <div class="rounded-2xl border border-emerald-200/80 bg-emerald-50/90 px-4 py-3 text-center shadow-sm dark:border-emerald-900/50 dark:bg-emerald-950/30">
                        <p class="text-2xl font-bold text-emerald-700 dark:text-emerald-300">{{ $requestStats['completed'] }}</p>
                        <p class="text-xs font-medium text-emerald-700/80 dark:text-emerald-300/80">{{ __('Done') }}</p>
                    </div>
                    <div class="rounded-2xl border border-sky-200/80 bg-sky-50/90 px-4 py-3 text-center shadow-sm dark:border-sky-900/50 dark:bg-sky-950/30">
                        <p class="text-2xl font-bold text-sky-700 dark:text-sky-300">{{ $requestStats['approvals'] }}</p>
                        <p class="text-xs font-medium text-sky-700/80 dark:text-sky-300/80">{{ __('Approvals') }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if ($requests->isEmpty())
        <div class="flex flex-col items-center justify-center rounded-3xl border border-dashed border-zinc-200 bg-white px-6 py-16 text-center shadow-sm dark:border-zinc-700 dark:bg-zinc-900 sm:py-20">
            <span class="flex h-16 w-16 items-center justify-center rounded-2xl bg-zinc-100 ring-8 ring-zinc-50 dark:bg-zinc-800 dark:ring-zinc-950">
                <svg class="h-8 w-8 text-zinc-400 dark:text-zinc-500" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125"/>
                </svg>
            </span>
            <h2 class="mt-6 text-base font-semibold text-zinc-900 dark:text-zinc-50">{{ __('No sign requests') }}</h2>
            <p class="mt-2 max-w-sm text-sm leading-6 text-zinc-500 dark:text-zinc-400">
                {{ __('You are all caught up. New requests will appear here as soon as a document is assigned to you.') }}
            </p>
        </div>
    @else
        <section class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
            <div class="flex flex-col gap-2 border-b border-zinc-200 px-4 py-4 dark:border-zinc-800 sm:flex-row sm:items-center sm:justify-between lg:px-5">
                <div>
                    <h2 class="text-sm font-semibold uppercase tracking-[0.16em] text-zinc-500 dark:text-zinc-400">{{ __('Active queue') }}</h2>
                    <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                        {{ $requestStats['expiring'] > 0
                            ? __(':count request(s) expire soon. Prioritize those first.', ['count' => $requestStats['expiring']])
                            : __('Pending requests are sorted first so you can move quickly.') }}
                    </p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-800">
                    <thead class="bg-zinc-50 dark:bg-zinc-950/40">
                        <tr>
                            <th scope="col" class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400 lg:px-5">{{ __('Document') }}</th>
                            <th scope="col" class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('Status') }}</th>
                            <th scope="col" class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('Role') }}</th>
                            <th scope="col" class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('Method') }}</th>
                            <th scope="col" class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('Due') }}</th>
                            <th scope="col" class="px-4 py-3 text-right text-[11px] font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400 lg:px-5">{{ __('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 bg-white dark:divide-zinc-800 dark:bg-zinc-900">
                        @foreach ($requests as $signer)
                            @php
                                $doc = $signer->document;
                                $isPending = $signer->status === DocumentSignerStatus::Pending;
                                $isCompleted = in_array($signer->status, [DocumentSignerStatus::Signed, DocumentSignerStatus::Approved]);
                                $isActionable = $isPending && $doc->status === DocumentStatus::Pending;
                                $isExpiring = $signer->expires_at !== null && $signer->expires_at->between(now(), now()->addDays(2));
                                $signUrl = route('sign.account.show', ['signerId' => $signer->id]);
                                $roleLabel = $signer->isApprover() ? __('Approver') : __('Signer');
                                $methodLabel = match ($signer->signingMethod()) {
                                    SigningMethod::AccountVerified => __('Account verified'),
                                    SigningMethod::PkiCertificate => __('PKI certificate'),
                                    default => __('Email link'),
                                };
                            @endphp

                            <tr wire:key="sign-request-{{ $signer->id }}" class="transition hover:bg-zinc-50/80 dark:hover:bg-zinc-800/40">
                                <td class="min-w-[260px] px-4 py-4 lg:px-5">
                                    <div class="flex min-w-0 items-center gap-3">
                                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl
                                            {{ $isActionable ? 'bg-teal-50 text-teal-600 dark:bg-teal-950/50 dark:text-teal-300' : 'bg-zinc-100 text-zinc-400 dark:bg-zinc-800 dark:text-zinc-500' }}">
                                            <svg class="h-4.5 w-4.5" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/>
                                            </svg>
                                        </span>
                                        <div class="min-w-0">
                                            <p class="truncate text-sm font-semibold text-zinc-950 dark:text-zinc-50">{{ $doc->title }}</p>
                                            <p class="mt-0.5 truncate text-xs text-zinc-500 dark:text-zinc-400">{{ __('From :name', ['name' => $doc->user?->name ?? '—']) }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-4 py-4">
                                    <div class="flex flex-col items-start gap-1">
                                        @if ($isActionable)
                                            <span class="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-700 dark:bg-amber-950/50 dark:text-amber-300">{{ __('Pending') }}</span>
                                        @elseif ($isCompleted)
                                            <span class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300">{{ $signer->isApprover() ? __('Approved') : __('Signed') }}</span>
                                        @else
                                            <span class="inline-flex items-center rounded-full bg-zinc-100 px-2.5 py-1 text-xs font-semibold text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">{{ ucfirst($signer->status->value) }}</span>
                                        @endif

                                        @if ($isExpiring && $isActionable)
                                            <span class="inline-flex items-center rounded-full bg-red-100 px-2.5 py-1 text-xs font-semibold text-red-700 dark:bg-red-950/50 dark:text-red-300">{{ __('Expires soon') }}</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-4 py-4 text-sm font-semibold text-zinc-700 dark:text-zinc-200">{{ $roleLabel }}</td>
                                <td class="whitespace-nowrap px-4 py-4 text-sm text-zinc-600 dark:text-zinc-300">{{ $methodLabel }}</td>
                                <td class="whitespace-nowrap px-4 py-4">
                                    <p class="text-sm font-semibold text-zinc-700 dark:text-zinc-200">
                                        @if ($signer->expires_at)
                                            {{ $signer->expires_at->diffForHumans() }}
                                        @elseif ($doc->sent_at)
                                            {{ $doc->sent_at->diffForHumans() }}
                                        @else
                                            {{ __('Not sent') }}
                                        @endif
                                    </p>
                                    <p class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400">{{ $signer->expires_at ? __('Due') : __('Sent') }}</p>
                                </td>
                                <td class="whitespace-nowrap px-4 py-4 text-right lg:px-5">
                                    @if ($isActionable)
                                        <a href="{{ $signUrl }}"
                                           class="inline-flex items-center justify-center gap-2 rounded-xl bg-teal-600 px-4 py-2 text-sm font-semibold text-white shadow-sm shadow-teal-900/20 transition hover:bg-teal-500 focus:outline-none focus:ring-2 focus:ring-teal-400 focus:ring-offset-2 dark:bg-teal-500 dark:hover:bg-teal-400 dark:focus:ring-offset-zinc-900">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125"/>
                                            </svg>
                                            {{ $signer->isApprover() ? __('Approve') : __('Sign') }}
                                        </a>
                                    @elseif ($isCompleted)
                                        <a href="{{ $signUrl }}"
                                           class="inline-flex items-center justify-center rounded-xl border border-zinc-200 bg-white px-4 py-2 text-sm font-semibold text-zinc-700 transition hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800">
                                            {{ __('View') }}
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif

</div>
