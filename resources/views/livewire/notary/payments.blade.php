<?php

use App\Enums\NotaryRequestStatus;
use App\Enums\PaymentStatus;
use App\Models\NotaryRequest;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('components.layouts.app')] class extends Component {
    use WithPagination;

    #[Url(as: 'status')]
    public string $filterStatus = 'all';

    #[Url(as: 'period')]
    public string $filterPeriod = 'all';

    public function updatedFilterStatus(): void
    {
        $this->resetPage();
    }

    public function updatedFilterPeriod(): void
    {
        $this->resetPage();
    }

    protected function basePaymentQuery(): Builder
    {
        $user = Auth::user();
        abort_unless($user !== null, 403);

        return Payment::query()
            ->whereHas('notaryRequest', fn (Builder $query) => $query->where('notary_user_id', $user->id))
            ->with(['notaryRequest.requester']);
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        $baseQuery = $this->basePaymentQuery();

        $totalEarned = (clone $baseQuery)
            ->where('status', PaymentStatus::Paid->value)
            ->sum('amount');

        $thisMonthEarned = (clone $baseQuery)
            ->where('status', PaymentStatus::Paid->value)
            ->whereMonth('paid_at', now()->month)
            ->whereYear('paid_at', now()->year)
            ->sum('amount');

        $pendingAmount = (clone $baseQuery)
            ->whereNotIn('status', [PaymentStatus::Paid->value])
            ->whereHas('notaryRequest', function (Builder $query): void {
                $query->whereNotIn('status', [
                    NotaryRequestStatus::Rejected->value,
                    NotaryRequestStatus::Cancelled->value,
                    NotaryRequestStatus::Failed->value,
                ]);
            })
            ->sum('amount');

        $totalTransactions = (clone $baseQuery)
            ->where('status', PaymentStatus::Paid->value)
            ->count();

        $paymentsQuery = (clone $baseQuery)
            ->when($this->filterStatus !== 'all', function (Builder $query): void {
                $status = PaymentStatus::tryFrom($this->filterStatus);

                if ($status !== null) {
                    $query->where('status', $status->value);
                }
            })
            ->when($this->filterPeriod === 'this_month', function (Builder $query): void {
                $query->whereMonth('created_at', now()->month)
                    ->whereYear('created_at', now()->year);
            })
            ->when($this->filterPeriod === 'last_month', function (Builder $query): void {
                $lastMonth = now()->subMonthNoOverflow();

                $query->whereMonth('created_at', $lastMonth->month)
                    ->whereYear('created_at', $lastMonth->year);
            })
            ->when($this->filterPeriod === 'this_year', fn (Builder $query) => $query->whereYear('created_at', now()->year))
            ->latest('created_at');

        $user = Auth::user();
        abort_unless($user !== null, 403);

        $caseStatuses = NotaryRequest::query()
            ->with(['attorneyNotarialRegistry', 'payments', 'requester'])
            ->where('notary_user_id', $user->id)
            ->latest('updated_at')
            ->limit(8)
            ->get()
            ->map(function (NotaryRequest $notaryRequest): array {
                $latestPayment = $notaryRequest->payments->first();
                $registryFee = (float) ($notaryRequest->attorneyNotarialRegistry?->fees ?? 0);
                $latestStatus = $latestPayment?->status instanceof PaymentStatus ? $latestPayment->status : null;

                $paymentState = match (true) {
                    $latestStatus === PaymentStatus::Paid => 'paid',
                    $latestPayment === null && $registryFee <= 0 => 'not_required',
                    default => 'pending',
                };

                return [
                    'amount' => $latestPayment?->amount ?? $registryFee,
                    'client' => $notaryRequest->requester?->name ?? '—',
                    'route' => route('notary.requests.show', $notaryRequest),
                    'status' => $paymentState,
                    'title' => $notaryRequest->title,
                ];
            });

        return [
            'caseStatuses' => $caseStatuses,
            'payments' => $paymentsQuery->paginate(15),
            'pendingAmount' => $pendingAmount,
            'thisMonthEarned' => $thisMonthEarned,
            'totalEarned' => $totalEarned,
            'totalTransactions' => $totalTransactions,
        ];
    }
}; ?>

<div class="flex min-h-full w-full flex-1 flex-col gap-6 p-1">
    <header>
        <h1 class="text-2xl font-semibold tracking-tight text-zinc-900 dark:text-zinc-50 sm:text-3xl">
            {{ __('Payments') }}
        </h1>
        <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
            {{ __('Fees collected across all your notarization cases') }}
        </p>
    </header>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="ui-panel p-5">
            <div class="flex items-start justify-between gap-4">
                <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Total Earned') }}</p>
                <div class="rounded-lg bg-emerald-50 p-2 dark:bg-emerald-500/10">
                    <flux:icon name="banknotes" class="size-4 text-emerald-600 dark:text-emerald-400" />
                </div>
            </div>
            <p class="mt-2 text-2xl font-bold tabular-nums text-zinc-900 dark:text-zinc-50">
                ₱{{ number_format((float) $totalEarned, 2) }}
            </p>
            <p class="mt-1 text-xs text-zinc-400 dark:text-zinc-500">{{ __('All time') }}</p>
        </div>

        <div class="ui-panel p-5">
            <div class="flex items-start justify-between gap-4">
                <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('This Month') }}</p>
                <div class="rounded-lg bg-teal-50 p-2 dark:bg-teal-500/10">
                    <flux:icon name="calendar" class="size-4 text-teal-600 dark:text-teal-400" />
                </div>
            </div>
            <p class="mt-2 text-2xl font-bold tabular-nums text-zinc-900 dark:text-zinc-50">
                ₱{{ number_format((float) $thisMonthEarned, 2) }}
            </p>
            <p class="mt-1 text-xs text-zinc-400 dark:text-zinc-500">{{ now()->format('F Y') }}</p>
        </div>

        <div class="ui-panel p-5">
            <div class="flex items-start justify-between gap-4">
                <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Pending') }}</p>
                <div class="rounded-lg bg-amber-50 p-2 dark:bg-amber-500/10">
                    <flux:icon name="clock" class="size-4 text-amber-600 dark:text-amber-400" />
                </div>
            </div>
            <p class="mt-2 text-2xl font-bold tabular-nums text-zinc-900 dark:text-zinc-50">
                ₱{{ number_format((float) $pendingAmount, 2) }}
            </p>
            <p class="mt-1 text-xs text-zinc-400 dark:text-zinc-500">{{ __('Awaiting client payment') }}</p>
        </div>

        <div class="ui-panel p-5">
            <div class="flex items-start justify-between gap-4">
                <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Transactions') }}</p>
                <div class="rounded-lg bg-violet-50 p-2 dark:bg-violet-500/10">
                    <flux:icon name="receipt-percent" class="size-4 text-violet-600 dark:text-violet-400" />
                </div>
            </div>
            <p class="mt-2 text-2xl font-bold tabular-nums text-zinc-900 dark:text-zinc-50">
                {{ number_format($totalTransactions) }}
            </p>
            <p class="mt-1 text-xs text-zinc-400 dark:text-zinc-500">{{ __('Completed payments') }}</p>
        </div>
    </div>

    <div class="ui-panel overflow-hidden">
        <div class="border-b border-zinc-200 p-4 dark:border-zinc-700">
            <h2 class="text-sm font-semibold text-zinc-900 dark:text-zinc-50">{{ __('Per-case payment status') }}</h2>
            <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ __('Recent notary cases marked as paid, pending, or not required based on payments and registry fees.') }}</p>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="border-b border-zinc-200 bg-zinc-50 text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:border-zinc-700 dark:bg-zinc-900/50 dark:text-zinc-400">
                    <tr>
                        <th class="px-4 py-3">{{ __('Case') }}</th>
                        <th class="px-4 py-3">{{ __('Client') }}</th>
                        <th class="px-4 py-3">{{ __('Fee') }}</th>
                        <th class="px-4 py-3">{{ __('Payment status') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @forelse ($caseStatuses as $caseStatus)
                        @php
                            $caseBadge = match ($caseStatus['status']) {
                                'paid' => [__('Paid'), 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300'],
                                'pending' => [__('Pending'), 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300'],
                                default => [__('Not required'), 'bg-zinc-100 text-zinc-600 dark:bg-zinc-700 dark:text-zinc-300'],
                            };
                        @endphp
                        <tr
                            onclick="window.location='{{ $caseStatus['route'] }}'"
                            wire:key="case-payment-status-{{ md5($caseStatus['route']) }}"
                            class="cursor-pointer transition-colors hover:bg-zinc-50/60 dark:hover:bg-zinc-900/40"
                        >
                            <td class="max-w-[260px] px-4 py-3.5">
                                <p class="truncate font-medium text-zinc-900 dark:text-zinc-50">{{ $caseStatus['title'] }}</p>
                            </td>
                            <td class="px-4 py-3.5 text-zinc-700 dark:text-zinc-300">{{ $caseStatus['client'] }}</td>
                            <td class="px-4 py-3.5 font-semibold tabular-nums text-zinc-900 dark:text-zinc-50">
                                @if ((float) $caseStatus['amount'] > 0)
                                    ₱{{ number_format((float) $caseStatus['amount'], 2) }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-4 py-3.5">
                                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium {{ $caseBadge[1] }}">
                                    {{ $caseBadge[0] }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-8 text-center text-sm text-zinc-400 dark:text-zinc-500">
                                {{ __('No notary cases found') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="ui-panel overflow-hidden">
        <div class="flex flex-wrap items-center gap-3 border-b border-zinc-200 p-4 dark:border-zinc-700">
            <flux:select wire:model.live="filterStatus" class="w-40" aria-label="{{ __('Payment status') }}">
                <flux:select.option value="all">{{ __('All statuses') }}</flux:select.option>
                <flux:select.option value="paid">{{ __('Paid') }}</flux:select.option>
                <flux:select.option value="pending">{{ __('Pending') }}</flux:select.option>
            </flux:select>

            <flux:select wire:model.live="filterPeriod" class="w-40" aria-label="{{ __('Payment period') }}">
                <flux:select.option value="all">{{ __('All time') }}</flux:select.option>
                <flux:select.option value="this_month">{{ __('This month') }}</flux:select.option>
                <flux:select.option value="last_month">{{ __('Last month') }}</flux:select.option>
                <flux:select.option value="this_year">{{ __('This year') }}</flux:select.option>
            </flux:select>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="border-b border-zinc-200 bg-zinc-50 text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:border-zinc-700 dark:bg-zinc-900/50 dark:text-zinc-400">
                    <tr>
                        <th class="px-4 py-3">{{ __('Case') }}</th>
                        <th class="px-4 py-3">{{ __('Client') }}</th>
                        <th class="px-4 py-3">{{ __('Amount') }}</th>
                        <th class="px-4 py-3">{{ __('Status') }}</th>
                        <th class="px-4 py-3">{{ __('Gateway') }}</th>
                        <th class="px-4 py-3">{{ __('Date') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @forelse ($payments as $payment)
                        @php
                            $statusValue = $payment->status instanceof PaymentStatus ? $payment->status->value : (string) $payment->status;
                            $statusBadge = match ($statusValue) {
                                PaymentStatus::Paid->value => [__('Paid'), 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300'],
                                PaymentStatus::Pending->value => [__('Pending'), 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300'],
                                PaymentStatus::Failed->value => [__('Failed'), 'bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-300'],
                                PaymentStatus::Expired->value => [__('Expired'), 'bg-zinc-100 text-zinc-600 dark:bg-zinc-700 dark:text-zinc-300'],
                                PaymentStatus::Cancelled->value => [__('Cancelled'), 'bg-zinc-100 text-zinc-600 dark:bg-zinc-700 dark:text-zinc-300'],
                                default => [str($statusValue)->replace('_', ' ')->title(), 'bg-zinc-100 text-zinc-600 dark:bg-zinc-700 dark:text-zinc-300'],
                            };
                            $paymentDate = $payment->paid_at ?? $payment->created_at;
                        @endphp
                        <tr
                            @if ($payment->notaryRequest)
                                onclick="window.location='{{ route('notary.requests.show', $payment->notaryRequest) }}'"
                            @endif
                            wire:key="payment-{{ $payment->id }}"
                            class="transition-colors hover:bg-zinc-50/60 dark:hover:bg-zinc-900/40 {{ $payment->notaryRequest ? 'cursor-pointer' : '' }}"
                        >
                            <td class="max-w-[220px] px-4 py-3.5">
                                <p class="truncate font-medium text-zinc-900 dark:text-zinc-50">
                                    {{ $payment->notaryRequest?->title ?? '—' }}
                                </p>
                                <p class="font-mono text-xs text-zinc-400 dark:text-zinc-500">
                                    {{ $payment->reference ?? '—' }}
                                </p>
                            </td>
                            <td class="px-4 py-3.5 text-zinc-700 dark:text-zinc-300">
                                {{ $payment->notaryRequest?->requester?->name ?? '—' }}
                            </td>
                            <td class="px-4 py-3.5 font-semibold tabular-nums text-zinc-900 dark:text-zinc-50">
                                ₱{{ number_format((float) $payment->amount, 2) }}
                            </td>
                            <td class="px-4 py-3.5">
                                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium {{ $statusBadge[1] }}">
                                    {{ $statusBadge[0] }}
                                </span>
                            </td>
                            <td class="px-4 py-3.5 capitalize text-zinc-500 dark:text-zinc-400">
                                {{ $payment->gateway ?? '—' }}
                            </td>
                            <td class="px-4 py-3.5 text-xs text-zinc-500 dark:text-zinc-400">
                                {{ $paymentDate?->format('M j, Y') ?? '—' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-12 text-center">
                                <flux:icon name="banknotes" class="mx-auto mb-2 size-8 text-zinc-300 dark:text-zinc-600" />
                                <p class="text-sm text-zinc-400 dark:text-zinc-500">
                                    {{ __('No payments found') }}
                                </p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($payments->hasPages())
            <div class="border-t border-zinc-200 px-4 py-3 dark:border-zinc-700">
                {{ $payments->links() }}
            </div>
        @endif
    </div>
</div>
