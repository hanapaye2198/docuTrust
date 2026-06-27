<?php

use App\Enums\DocumentSignerStatus;
use App\Enums\DocumentStatus;
use App\Enums\SigningMethod;
use App\Models\DocumentSigner;
use App\Services\SigningMethodService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {

    public function with(): array
    {
        $userId = Auth::id();

        $requests = DocumentSigner::query()
            ->with(['document.user', 'document.documentSigners'])
            ->where('user_id', $userId)
            ->whereHas('document', fn ($q) => $q->whereIn('status', [
                DocumentStatus::Pending,
                DocumentStatus::Completed,
            ]))
            ->orderByRaw("CASE WHEN status = ? THEN 0 ELSE 1 END", [DocumentSignerStatus::Pending->value])
            ->orderByDesc('id')
            ->get();

        return ['requests' => $requests];
    }
}; ?>

<div class="flex w-full min-w-0 flex-col gap-6 p-1">

    {{-- ── Header ── --}}
    <div>
        <h1 class="text-xl font-bold tracking-tight text-zinc-900 dark:text-zinc-50 sm:text-2xl">
            {{ __('Sign Requests') }}
        </h1>
        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
            {{ __('Documents that require your signature or approval.') }}
        </p>
    </div>

    {{-- ── Request cards ── --}}
    @if ($requests->isEmpty())
        <div class="flex flex-col items-center justify-center rounded-2xl border border-dashed border-zinc-200 bg-white px-6 py-16 text-center dark:border-zinc-700 dark:bg-zinc-900">
            <span class="flex h-14 w-14 items-center justify-center rounded-full bg-zinc-100 dark:bg-zinc-800">
                <svg class="h-7 w-7 text-zinc-400 dark:text-zinc-500" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125"/>
                </svg>
            </span>
            <p class="mt-4 text-sm font-medium text-zinc-600 dark:text-zinc-300">{{ __('No sign requests') }}</p>
            <p class="mt-1 text-xs text-zinc-400 dark:text-zinc-500">{{ __('You have no pending documents to sign or approve.') }}</p>
        </div>
    @else
        <div class="space-y-3">
            @foreach ($requests as $signer)
                @php
                    $doc = $signer->document;
                    $isPending = $signer->status === DocumentSignerStatus::Pending;
                    $isCompleted = in_array($signer->status, [DocumentSignerStatus::Signed, DocumentSignerStatus::Approved]);
                    $signUrl = route('sign.account.show', ['signerId' => $signer->id]);
                @endphp

                <div class="overflow-hidden rounded-2xl border bg-white shadow-sm transition dark:bg-zinc-900
                    {{ $isPending ? 'border-teal-200 dark:border-teal-800/60' : 'border-zinc-200 dark:border-zinc-700' }}">

                    <div class="flex items-start gap-4 p-4 sm:p-5">
                        {{-- Document icon --}}
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl
                            {{ $isPending ? 'bg-teal-50 dark:bg-teal-900/30' : 'bg-zinc-100 dark:bg-zinc-800' }}">
                            <svg class="h-5 w-5 {{ $isPending ? 'text-teal-600 dark:text-teal-400' : 'text-zinc-400' }}"
                                 fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/>
                            </svg>
                        </span>

                        {{-- Content --}}
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-start gap-2">
                                <p class="min-w-0 flex-1 truncate text-sm font-semibold text-zinc-900 dark:text-zinc-50">
                                    {{ $doc->title }}
                                </p>

                                {{-- Status badge --}}
                                @if ($isPending)
                                    <span class="shrink-0 rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-700 dark:bg-amber-900/30 dark:text-amber-300">
                                        {{ __('Pending') }}
                                    </span>
                                @elseif ($isCompleted)
                                    <span class="shrink-0 rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300">
                                        {{ $signer->isApprover() ? __('Approved') : __('Signed') }}
                                    </span>
                                @endif
                            </div>

                            <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-zinc-500 dark:text-zinc-400">
                                <span>{{ __('From: :name', ['name' => $doc->user?->name ?? '—']) }}</span>
                                <span>·</span>
                                <span>{{ $signer->isApprover() ? __('Approver') : __('Signer') }}</span>
                                @if ($doc->sent_at)
                                    <span>·</span>
                                    <span>{{ __('Sent :date', ['date' => $doc->sent_at->diffForHumans()]) }}</span>
                                @endif
                            </div>
                        </div>

                        {{-- Action --}}
                        <div class="shrink-0">
                            @if ($isPending && $doc->status === DocumentStatus::Pending)
                                <a href="{{ $signUrl }}"
                                   class="inline-flex items-center gap-1.5 rounded-xl bg-teal-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-teal-700 dark:bg-teal-500 dark:hover:bg-teal-600">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125"/>
                                    </svg>
                                    {{ $signer->isApprover() ? __('Approve') : __('Sign') }}
                                </a>
                            @elseif ($isCompleted)
                                <a href="{{ $signUrl }}"
                                   class="inline-flex items-center gap-1.5 rounded-xl border border-zinc-200 bg-white px-4 py-2 text-sm font-medium text-zinc-600 transition hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300 dark:hover:bg-zinc-800">
                                    {{ __('View') }}
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

</div>
