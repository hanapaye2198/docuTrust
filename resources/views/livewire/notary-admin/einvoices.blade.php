<?php

use App\Enums\EInvoiceStatus;
use App\Models\EInvoice;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('components.layouts.app')] class extends Component {
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'status')]
    public string $statusFilter = 'all';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    protected function baseInvoiceQuery(User $user): Builder
    {
        return EInvoice::query()
            ->with(['organization', 'notaryRequest.requester', 'payment', 'latestSubmission'])
            ->when($user->isNotaryAdmin(), fn (Builder $builder) => $builder->where('organization_id', $user->organization_id))
            ->when($this->search !== '', function (Builder $builder): void {
                $builder->where(function (Builder $nested): void {
                    $nested
                        ->where('invoice_number', 'like', '%'.$this->search.'%')
                        ->orWhere('submit_id', 'like', '%'.$this->search.'%')
                        ->orWhere('eis_unique_id', 'like', '%'.$this->search.'%')
                        ->orWhere('document_title', 'like', '%'.$this->search.'%')
                        ->orWhere('seller_name', 'like', '%'.$this->search.'%')
                        ->orWhere('buyer_name', 'like', '%'.$this->search.'%')
                        ->orWhereHas('organization', fn (Builder $organization) => $organization->where('name', 'like', '%'.$this->search.'%'))
                        ->orWhereHas('notaryRequest.requester', fn (Builder $requester) => $requester->where('name', 'like', '%'.$this->search.'%'));
                });
            });
    }

    public function with(): array
    {
        $user = Auth::user();
        abort_unless($user !== null, 401);

        $query = $this->baseInvoiceQuery($user)
            ->when($this->statusFilter !== 'all', fn (Builder $builder) => $builder->where('status', $this->statusFilter))
            ->latest('created_at');

        $statusCounts = $this->baseInvoiceQuery($user)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'invoices' => $query->paginate(12),
            'totalInvoices' => $statusCounts->sum(),
            'acceptedCount' => (int) ($statusCounts[EInvoiceStatus::Accepted->value] ?? 0),
            'inFlightCount' => (int) ($statusCounts[EInvoiceStatus::Submitted->value] ?? 0) + (int) ($statusCounts[EInvoiceStatus::Processing->value] ?? 0),
            'actionRequiredCount' => (int) ($statusCounts[EInvoiceStatus::NeedsCorrection->value] ?? 0) + (int) ($statusCounts[EInvoiceStatus::Rejected->value] ?? 0),
            'queuedCount' => (int) ($statusCounts[EInvoiceStatus::Queued->value] ?? 0),
        ];
    }
}; ?>

<div class="mx-auto flex w-full max-w-7xl min-w-0 flex-col gap-6 px-2 py-4 sm:px-4 lg:px-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-zinc-900 dark:text-white">{{ __('E-Invoices') }}</h1>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Monitor invoice submission state, EIS results, and notarizations that need billing or response follow-up.') }}</p>
        </div>
        <div class="text-sm font-medium text-zinc-400 dark:text-zinc-500">
            {{ now()->format('l, F j, Y') }}
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-xl bg-white p-5 shadow-sm dark:bg-zinc-900">
            <div class="text-xs font-semibold uppercase tracking-wider text-zinc-400 dark:text-zinc-500">{{ __('Total Invoices') }}</div>
            <div class="mt-2 text-3xl font-bold tabular-nums text-zinc-900 dark:text-white">{{ $totalInvoices }}</div>
        </div>
        <div class="rounded-xl bg-white p-5 shadow-sm dark:bg-zinc-900">
            <div class="text-xs font-semibold uppercase tracking-wider text-zinc-400 dark:text-zinc-500">{{ __('Accepted') }}</div>
            <div class="mt-2 text-3xl font-bold tabular-nums text-emerald-600 dark:text-emerald-400">{{ $acceptedCount }}</div>
        </div>
        <div class="rounded-xl bg-white p-5 shadow-sm dark:bg-zinc-900">
            <div class="text-xs font-semibold uppercase tracking-wider text-zinc-400 dark:text-zinc-500">{{ __('In Flight') }}</div>
            <div class="mt-2 text-3xl font-bold tabular-nums text-sky-600 dark:text-sky-400">{{ $inFlightCount }}</div>
        </div>
        <div class="rounded-xl bg-white p-5 shadow-sm dark:bg-zinc-900">
            <div class="text-xs font-semibold uppercase tracking-wider text-zinc-400 dark:text-zinc-500">{{ __('Needs Attention') }}</div>
            <div class="mt-2 text-3xl font-bold tabular-nums text-amber-600 dark:text-amber-400">{{ $actionRequiredCount + $queuedCount }}</div>
        </div>
    </div>

    <div class="rounded-xl bg-white p-6 shadow-sm dark:bg-zinc-900">
        <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_220px]">
            <flux:field>
                <flux:label>{{ __('Search') }}</flux:label>
                <flux:input wire:model.live.debounce.300ms="search" type="text" placeholder="{{ __('Invoice number, case, client, submit ID, or EIS unique ID') }}" />
            </flux:field>
            <flux:field>
                <flux:label>{{ __('Status') }}</flux:label>
                <select wire:model.live="statusFilter" class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2.5 text-sm text-zinc-900 shadow-sm dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100">
                    <option value="all">{{ __('All statuses') }}</option>
                    @foreach (App\Enums\EInvoiceStatus::cases() as $status)
                        <option value="{{ $status->value }}">{{ str($status->value)->replace('_', ' ')->title() }}</option>
                    @endforeach
                </select>
            </flux:field>
        </div>

        @if ($invoices->isEmpty())
            <div class="mt-6 rounded-xl border border-dashed border-zinc-300 px-4 py-10 text-center text-sm text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
                {{ __('No e-invoices match the current filters.') }}
            </div>
        @else
            <div class="mt-6 overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-800">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-800">
                        <thead class="bg-zinc-50 dark:bg-zinc-950/40">
                            <tr class="text-left text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                                <th class="px-4 py-3">{{ __('Invoice') }}</th>
                                <th class="px-4 py-3">{{ __('Case') }}</th>
                                <th class="px-4 py-3">{{ __('Status') }}</th>
                                <th class="px-4 py-3">{{ __('Identifiers') }}</th>
                                <th class="px-4 py-3">{{ __('Amount') }}</th>
                                <th class="px-4 py-3">{{ __('Updated') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 bg-white dark:divide-zinc-800 dark:bg-zinc-900">
                            @foreach ($invoices as $invoice)
                                @php
                                    $badge = match ($invoice->status) {
                                        EInvoiceStatus::Accepted => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/30 dark:text-emerald-300',
                                        EInvoiceStatus::Rejected, EInvoiceStatus::NeedsCorrection => 'bg-red-50 text-red-700 dark:bg-red-950/30 dark:text-red-300',
                                        EInvoiceStatus::Queued, EInvoiceStatus::Submitted, EInvoiceStatus::Processing => 'bg-sky-50 text-sky-700 dark:bg-sky-950/30 dark:text-sky-300',
                                        default => 'bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300',
                                    };
                                    $latestSubmission = $invoice->latestSubmission;
                                @endphp
                                <tr class="align-top">
                                    <td class="px-4 py-4">
                                        <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $invoice->invoice_number }}</div>
                                        <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ $invoice->organization?->name ?? '—' }}</div>
                                    </td>
                                    <td class="px-4 py-4">
                                        <div class="font-medium text-zinc-800 dark:text-zinc-200">
                                            <a href="{{ route('notary-requests.show', $invoice->notaryRequest) }}" wire:navigate class="transition hover:text-teal-600 dark:hover:text-teal-400">
                                                {{ $invoice->document_title ?? $invoice->notaryRequest?->title ?? __('Case #:id', ['id' => $invoice->notary_request_id]) }}
                                            </a>
                                        </div>
                                        <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                                            {{ __('Client') }}: {{ $invoice->notaryRequest?->requester?->name ?? $invoice->buyer_name ?? '—' }}
                                        </div>
                                        @if ($invoice->error_message)
                                            <div class="mt-2 text-xs text-red-600 dark:text-red-400">{{ $invoice->error_message }}</div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-4">
                                        <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold uppercase {{ $badge }}">{{ str($invoice->status->value)->replace('_', ' ') }}</span>
                                    </td>
                                    <td class="px-4 py-4 text-xs text-zinc-500 dark:text-zinc-400">
                                        <div>{{ __('Submit ID') }}: <span class="font-mono text-zinc-700 dark:text-zinc-300">{{ $invoice->submit_id ?? '—' }}</span></div>
                                        <div class="mt-1">{{ __('EIS ID') }}: <span class="font-mono text-zinc-700 dark:text-zinc-300">{{ $invoice->eis_unique_id ?? '—' }}</span></div>
                                        @if ($latestSubmission)
                                            <div class="mt-1">{{ __('Latest audit') }}: <span class="uppercase">{{ $latestSubmission->status }}</span></div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-4">
                                        <div class="font-medium text-zinc-900 dark:text-zinc-100">PHP {{ number_format((float) $invoice->total_amount, 2) }}</div>
                                        <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ $invoice->issue_date?->timezone('Asia/Manila')->format('M j, Y g:i A') ?? '—' }} (PHT)</div>
                                    </td>
                                    <td class="px-4 py-4 text-xs text-zinc-500 dark:text-zinc-400">
                                        <div>{{ $invoice->updated_at?->diffForHumans() ?? '—' }}</div>
                                        <div class="mt-1">{{ $invoice->updated_at?->timezone('Asia/Manila')->format('M j, Y g:i A') ?? '—' }} (PHT)</div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-4">
                {{ $invoices->links() }}
            </div>
        @endif
    </div>
</div>
