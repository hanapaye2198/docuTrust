<?php

use App\Enums\UserRole;
use App\Http\Requests\StoreNotaryAvailabilitySlotRequest;
use App\Models\NotaryAppointment;
use App\Models\NotaryAvailabilitySlot;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    public string $currentMonth = '';

    public ?string $selectedDate = null;

    public bool $showAddSlotModal = false;

    public string $newSlotStartTime = '09:00';

    public string $newSlotEndTime = '10:00';

    public int $newSlotDuration = 60;

    public bool $repeatWeekly = false;

    public int $repeatWeeks = 4;

    public function mount(): void
    {
        abort_unless(Auth::user()?->role === UserRole::Notary, 403);

        $this->currentMonth = now()->startOfMonth()->toDateString();
        $this->selectedDate = now()->toDateString();
    }

    public function addSlot(): void
    {
        $user = Auth::user();
        abort_unless($user !== null && $user->role === UserRole::Notary, 403);

        $validated = $this->validate(StoreNotaryAvailabilitySlotRequest::livewireRules());
        $dates = [$validated['selectedDate']];

        if ($this->repeatWeekly) {
            for ($week = 1; $week < $this->repeatWeeks; $week++) {
                $dates[] = Carbon::parse($validated['selectedDate'])->addWeeks($week)->toDateString();
            }
        }

        foreach ($dates as $date) {
            NotaryAvailabilitySlot::query()->create([
                'notary_user_id' => $user->id,
                'date' => $date,
                'start_time' => $validated['newSlotStartTime'],
                'end_time' => $validated['newSlotEndTime'],
                'duration_minutes' => $validated['newSlotDuration'],
            ]);
        }

        $this->showAddSlotModal = false;
        $this->reset(['newSlotStartTime', 'newSlotEndTime', 'newSlotDuration', 'repeatWeekly', 'repeatWeeks']);
        session()->flash('status', __('Availability slot saved.'));
    }

    public function confirmAppointment(int $appointmentId): void
    {
        $appointment = NotaryAppointment::query()
            ->where('notary_user_id', Auth::id())
            ->whereKey($appointmentId)
            ->firstOrFail();

        $appointment->update([
            'status' => 'confirmed',
            'confirmed_at' => now(),
        ]);

        session()->flash('status', __('Appointment confirmed.'));
    }

    public function cancelAppointment(int $appointmentId, string $reason = ''): void
    {
        $appointment = NotaryAppointment::query()
            ->where('notary_user_id', Auth::id())
            ->whereKey($appointmentId)
            ->firstOrFail();

        $appointment->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancellation_reason' => $reason !== '' ? $reason : null,
        ]);

        $appointment->slot()->update(['is_booked' => false]);
        session()->flash('status', __('Appointment declined.'));
    }

    public function deleteSlot(int $slotId): void
    {
        NotaryAvailabilitySlot::query()
            ->where('notary_user_id', Auth::id())
            ->where('is_booked', false)
            ->whereKey($slotId)
            ->firstOrFail()
            ->delete();

        session()->flash('status', __('Availability slot removed.'));
    }

    public function previousMonth(): void
    {
        $this->currentMonth = Carbon::parse($this->currentMonth)->subMonthNoOverflow()->startOfMonth()->toDateString();
    }

    public function nextMonth(): void
    {
        $this->currentMonth = Carbon::parse($this->currentMonth)->addMonthNoOverflow()->startOfMonth()->toDateString();
    }

    public function goToToday(): void
    {
        $this->currentMonth = now()->startOfMonth()->toDateString();
        $this->selectedDate = now()->toDateString();
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        $user = Auth::user();
        abort_unless($user !== null && $user->role === UserRole::Notary, 403);

        $calendarMonth = Carbon::parse($this->currentMonth)->startOfMonth();

        return [
            'calendarMonth' => $calendarMonth,
            'calendarSlots' => NotaryAvailabilitySlot::query()
                ->where('notary_user_id', $user->id)
                ->whereBetween('date', [
                    $calendarMonth->copy()->startOfMonth()->toDateString(),
                    $calendarMonth->copy()->endOfMonth()->toDateString(),
                ])
                ->with('appointment.client')
                ->get()
                ->groupBy(fn (NotaryAvailabilitySlot $slot) => $slot->date->format('Y-m-d')),
            'upcomingAppointments' => NotaryAppointment::query()
                ->where('notary_appointments.notary_user_id', $user->id)
                ->whereIn('notary_appointments.status', ['pending', 'confirmed'])
                ->whereHas('slot', fn (Builder $query) => $query->where('date', '>=', today()))
                ->with(['slot', 'client', 'notaryRequest'])
                ->join('notary_availability_slots', 'notary_appointments.notary_availability_slot_id', '=', 'notary_availability_slots.id')
                ->orderBy('notary_availability_slots.date')
                ->orderBy('notary_availability_slots.start_time')
                ->select('notary_appointments.*')
                ->limit(5)
                ->get(),
        ];
    }
}; ?>

@php
    /** @var Collection<string, Collection<int, NotaryAvailabilitySlot>> $calendarSlots */
    $startOfMonth = $calendarMonth->copy()->startOfMonth();
    $startPad = $startOfMonth->dayOfWeekIso - 1;
    $daysInMonth = $calendarMonth->daysInMonth;
    $today = now()->toDateString();
    $monthSlots = $calendarSlots->flatMap(fn (Collection $daySlots) => $daySlots);
    $monthAvailableCount = $monthSlots->where('is_booked', false)->where('is_blocked', false)->count();
    $monthBookedCount = $monthSlots->where('is_booked', true)->count();
    $selectedDateLabel = $selectedDate ? Carbon::parse($selectedDate)->format('l, F j') : __('Select a date');
@endphp

<x-admin.page gap="gap-7">
    <section class="relative overflow-hidden rounded-3xl border border-teal-200/60 bg-gradient-to-br from-teal-50 via-white to-indigo-50/80 p-5 shadow-[0_16px_50px_rgb(20_184_166/0.10)] ring-1 ring-teal-500/10 sm:p-7 dark:border-teal-500/20 dark:from-teal-950/40 dark:via-zinc-900 dark:to-indigo-950/30 dark:shadow-[0_18px_60px_rgb(0_0_0/0.35)] dark:ring-teal-400/10">
        <div class="pointer-events-none absolute -end-16 -top-20 size-64 rounded-full bg-teal-400/15 blur-3xl dark:bg-teal-400/20" aria-hidden="true"></div>
        <div class="pointer-events-none absolute -bottom-24 start-10 size-56 rounded-full bg-indigo-400/10 blur-3xl dark:bg-indigo-400/10" aria-hidden="true"></div>

        <div class="relative flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
            <div class="min-w-0">
                <p class="text-xs font-bold uppercase tracking-[0.16em] text-teal-700/80 dark:text-teal-300/90">{{ __('Attorney calendar') }}</p>
                <h1 class="mt-2 text-2xl font-bold tracking-tight text-zinc-950 sm:text-3xl dark:text-white">{{ __('Manage your schedule') }}</h1>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-zinc-600 dark:text-zinc-400">
                    {{ __('Open availability, review bookings, and keep every notary session easy for clients to reserve.') }}
                </p>
            </div>

            <div class="flex flex-col gap-2 sm:flex-row lg:justify-end">
                <flux:button wire:click="goToToday" variant="ghost" icon="calendar-days" class="bg-white/70 dark:bg-white/5">
                    {{ __('Today') }}
                </flux:button>
                <flux:button wire:click="$set('showAddSlotModal', true)" variant="primary" icon="plus">
                    {{ __('Add availability') }}
                </flux:button>
            </div>
        </div>

        <div class="relative mt-6 grid gap-3 sm:grid-cols-3">
            <div class="rounded-2xl border border-white/70 bg-white/75 p-4 shadow-sm backdrop-blur dark:border-white/10 dark:bg-white/[0.04]">
                <div class="flex items-center justify-between gap-3">
                    <p class="text-xs font-semibold uppercase tracking-widest text-zinc-500 dark:text-zinc-400">{{ __('Available') }}</p>
                    <span class="flex size-9 items-center justify-center rounded-xl bg-teal-100 text-teal-700 dark:bg-teal-500/15 dark:text-teal-300">
                        <flux:icon.clock variant="mini" class="size-4" />
                    </span>
                </div>
                <p class="mt-3 text-3xl font-bold tabular-nums text-zinc-950 dark:text-white">{{ $monthAvailableCount }}</p>
                <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ __('open slots this month') }}</p>
            </div>

            <div class="rounded-2xl border border-white/70 bg-white/75 p-4 shadow-sm backdrop-blur dark:border-white/10 dark:bg-white/[0.04]">
                <div class="flex items-center justify-between gap-3">
                    <p class="text-xs font-semibold uppercase tracking-widest text-zinc-500 dark:text-zinc-400">{{ __('Booked') }}</p>
                    <span class="flex size-9 items-center justify-center rounded-xl bg-violet-100 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300">
                        <flux:icon.user-group variant="mini" class="size-4" />
                    </span>
                </div>
                <p class="mt-3 text-3xl font-bold tabular-nums text-zinc-950 dark:text-white">{{ $monthBookedCount }}</p>
                <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ __('client bookings') }}</p>
            </div>

            <div class="rounded-2xl border border-white/70 bg-white/75 p-4 shadow-sm backdrop-blur dark:border-white/10 dark:bg-white/[0.04]">
                <div class="flex items-center justify-between gap-3">
                    <p class="text-xs font-semibold uppercase tracking-widest text-zinc-500 dark:text-zinc-400">{{ __('Upcoming') }}</p>
                    <span class="flex size-9 items-center justify-center rounded-xl bg-indigo-100 text-indigo-700 dark:bg-indigo-500/15 dark:text-indigo-300">
                        <flux:icon.bell-alert variant="mini" class="size-4" />
                    </span>
                </div>
                <p class="mt-3 text-3xl font-bold tabular-nums text-zinc-950 dark:text-white">{{ $upcomingAppointments->count() }}</p>
                <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ __('needs your attention') }}</p>
            </div>
        </div>
    </section>

    @if (session('status'))
        <flux:callout variant="success" icon="check-circle" heading="{{ session('status') }}" />
    @endif

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_380px]">
        <section class="overflow-hidden rounded-3xl border border-zinc-200/80 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
            <div class="flex flex-col gap-4 border-b border-zinc-100 bg-zinc-50/80 p-4 sm:flex-row sm:items-center sm:justify-between sm:p-5 dark:border-zinc-800 dark:bg-zinc-950/40">
                <div>
                    <h2 class="text-lg font-semibold text-zinc-950 dark:text-white">{{ $calendarMonth->format('F Y') }}</h2>
                    <p class="mt-0.5 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Tap any future day to manage its slots.') }}</p>
                </div>

                <div class="grid grid-cols-2 gap-2 sm:flex">
                    <flux:button wire:click="previousMonth" variant="ghost" size="sm" icon="chevron-left">
                        {{ __('Previous') }}
                    </flux:button>
                    <flux:button wire:click="nextMonth" variant="ghost" size="sm" icon="chevron-right" icon:trailing>
                        {{ __('Next') }}
                    </flux:button>
                </div>
            </div>

            <div class="grid grid-cols-7 border-b border-zinc-100 bg-white px-2 py-2 sm:px-4 dark:border-zinc-800 dark:bg-zinc-900">
                @foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $dayLabel)
                    <div class="py-2 text-center text-[11px] font-bold uppercase tracking-wider text-zinc-400 dark:text-zinc-500" wire:key="calendar-day-label-{{ $dayLabel }}">
                        {{ __($dayLabel) }}
                    </div>
                @endforeach
            </div>

            <div class="grid grid-cols-7 gap-px bg-zinc-100 p-px dark:bg-zinc-800">
                @for ($i = 0; $i < $startPad; $i++)
                    <div class="min-h-16 bg-zinc-50 sm:min-h-24 dark:bg-zinc-950/40" wire:key="calendar-pad-{{ $i }}"></div>
                @endfor

                @for ($day = 1; $day <= $daysInMonth; $day++)
                    @php
                        $dateStr = $calendarMonth->copy()->setDay($day)->toDateString();
                        $daySlots = $calendarSlots[$dateStr] ?? collect();
                        $bookedCount = $daySlots->where('is_booked', true)->count();
                        $availableCount = $daySlots->where('is_booked', false)->where('is_blocked', false)->count();
                        $isToday = $dateStr === $today;
                        $isPast = $dateStr < $today;
                    @endphp

                    <button
                        type="button"
                        wire:key="calendar-day-{{ $dateStr }}"
                        wire:click="$set('selectedDate', '{{ $dateStr }}')"
                        @disabled($isPast)
                        class="group relative flex min-h-16 flex-col items-start justify-between bg-white p-2 text-left transition sm:min-h-24 sm:p-3 {{ $isPast ? 'cursor-default opacity-45' : 'cursor-pointer hover:bg-teal-50/70 dark:hover:bg-teal-500/10' }} {{ $selectedDate === $dateStr ? 'bg-teal-50 ring-2 ring-inset ring-teal-500 dark:bg-teal-500/10' : '' }} dark:bg-zinc-900"
                    >
                        <span class="flex size-7 items-center justify-center rounded-full text-sm font-semibold transition {{ $isToday ? 'bg-teal-600 text-white shadow-sm shadow-teal-500/30' : 'text-zinc-700 group-hover:text-teal-700 dark:text-zinc-300 dark:group-hover:text-teal-300' }}">{{ $day }}</span>

                        @if ($daySlots->isNotEmpty())
                            <span class="flex w-full flex-wrap items-center gap-1">
                                @if ($availableCount > 0)
                                    <span class="inline-flex items-center gap-1 rounded-full bg-teal-100 px-1.5 py-0.5 text-[10px] font-bold text-teal-700 dark:bg-teal-500/15 dark:text-teal-300">
                                        <span class="size-1 rounded-full bg-teal-500"></span>
                                        {{ $availableCount }}
                                    </span>
                                @endif

                                @if ($bookedCount > 0)
                                    <span class="inline-flex items-center gap-1 rounded-full bg-violet-100 px-1.5 py-0.5 text-[10px] font-bold text-violet-700 dark:bg-violet-500/15 dark:text-violet-300">
                                        <span class="size-1 rounded-full bg-violet-500"></span>
                                        {{ $bookedCount }}
                                    </span>
                                @endif
                            </span>
                        @endif
                    </button>
                @endfor
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-zinc-100 bg-white px-4 py-3 dark:border-zinc-800 dark:bg-zinc-900">
                <div class="flex items-center gap-4">
                    <span class="flex items-center gap-1.5 text-xs font-medium text-zinc-500">
                    <span class="size-2 rounded-full bg-teal-400"></span>
                    {{ __('Available') }}
                    </span>
                    <span class="flex items-center gap-1.5 text-xs font-medium text-zinc-500">
                        <span class="size-2 rounded-full bg-violet-400"></span>
                        {{ __('Booked') }}
                    </span>
                </div>
                <p class="text-xs text-zinc-400 dark:text-zinc-500">{{ __('Mobile friendly: calendar stays seven columns.') }}</p>
            </div>
        </section>

        <aside class="space-y-4 xl:sticky xl:top-5 xl:self-start">
            @if ($selectedDate)
                <section class="overflow-hidden rounded-3xl border border-zinc-200/80 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                    <div class="mb-4 flex items-center justify-between gap-3">
                        <div class="p-5 pb-0">
                            <p class="text-xs font-bold uppercase tracking-widest text-teal-600 dark:text-teal-400">{{ __('Selected day') }}</p>
                            <h3 class="mt-1 text-lg font-semibold text-zinc-950 dark:text-white">{{ $selectedDateLabel }}</h3>
                        </div>

                        <flux:button wire:click="$set('showAddSlotModal', true)" size="sm" variant="ghost" icon="plus" class="me-5 mt-5">
                            {{ __('Add slot') }}
                        </flux:button>
                    </div>

                    @php $daySlots = $calendarSlots[$selectedDate] ?? collect(); @endphp

                    <div class="space-y-3 p-5 pt-1">
                        @forelse ($daySlots->sortBy('start_time') as $slot)
                            <div wire:key="selected-slot-{{ $slot->id }}" class="group rounded-2xl border border-zinc-200 bg-zinc-50/70 p-4 transition hover:border-teal-200 hover:bg-teal-50/60 dark:border-zinc-800 dark:bg-zinc-950/40 dark:hover:border-teal-500/30 dark:hover:bg-teal-500/10">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="text-sm font-semibold text-zinc-950 dark:text-white">
                                            {{ Carbon::parse($slot->start_time)->format('g:i A') }}
                                            -
                                            {{ Carbon::parse($slot->end_time)->format('g:i A') }}
                                        </p>

                                        @if ($slot->is_booked && $slot->appointment)
                                            <p class="mt-1 truncate text-xs text-violet-600 dark:text-violet-400">
                                                {{ $slot->appointment->client?->name ?? __('Client') }}
                                                - {{ ucfirst($slot->appointment->status) }}
                                            </p>
                                        @else
                                            <p class="mt-1 text-xs text-teal-600 dark:text-teal-400">{{ __('Open for booking') }}</p>
                                        @endif
                                    </div>

                                    <div class="flex items-center gap-2">
                                        <flux:badge size="sm" :color="$slot->is_booked ? 'violet' : 'emerald'">
                                            {{ $slot->is_booked ? __('Booked') : __('Available') }}
                                        </flux:badge>

                                        @if (! $slot->is_booked)
                                            <flux:button
                                                wire:click="deleteSlot({{ $slot->id }})"
                                                wire:confirm="{{ __('Remove this slot?') }}"
                                                size="xs"
                                                variant="ghost"
                                                icon="trash"
                                                class="text-red-500 hover:text-red-600"
                                                aria-label="{{ __('Remove slot') }}"
                                            />
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="rounded-2xl border border-dashed border-zinc-200 p-6 text-center dark:border-zinc-800">
                                <div class="mx-auto flex size-11 items-center justify-center rounded-2xl bg-zinc-100 text-zinc-400 dark:bg-zinc-800 dark:text-zinc-500">
                                    <flux:icon.calendar-days variant="mini" class="size-5" />
                                </div>
                                <p class="mt-3 text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ __('No slots on this day') }}</p>
                                <p class="mt-1 text-xs text-zinc-400">{{ __('Add availability so clients can book this date.') }}</p>
                            </div>
                        @endforelse
                    </div>
                </section>
            @endif

            <section class="overflow-hidden rounded-3xl border border-zinc-200/80 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                <div class="border-b border-zinc-100 p-5 dark:border-zinc-800">
                    <p class="text-xs font-bold uppercase tracking-widest text-violet-600 dark:text-violet-400">{{ __('Queue') }}</p>
                    <h3 class="mt-1 text-lg font-semibold text-zinc-950 dark:text-white">{{ __('Upcoming bookings') }}</h3>
                </div>

                <div class="space-y-3 p-5">
                    @forelse ($upcomingAppointments as $appointment)
                        <div wire:key="upcoming-appointment-{{ $appointment->id }}" class="rounded-2xl border border-zinc-200 bg-zinc-50/70 p-4 dark:border-zinc-800 dark:bg-zinc-950/40">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold text-zinc-950 dark:text-white">{{ $appointment->client?->name ?? __('Client') }}</p>
                                    <p class="mt-1 flex items-center gap-1.5 text-xs text-zinc-500 dark:text-zinc-400">
                                        <flux:icon.clock variant="mini" class="size-3.5" />
                                        {{ $appointment->slot->date->format('M j') }}
                                        -
                                        {{ Carbon::parse($appointment->slot->start_time)->format('g:i A') }}
                                    </p>
                                </div>

                                <flux:badge size="sm" :color="$appointment->status === 'confirmed' ? 'green' : 'amber'">
                                    {{ ucfirst($appointment->status) }}
                                </flux:badge>
                            </div>

                            @if ($appointment->status === 'pending')
                                <div class="mt-3 grid grid-cols-2 gap-2">
                                    <flux:button wire:click="confirmAppointment({{ $appointment->id }})" size="xs" variant="primary" class="justify-center">
                                        {{ __('Confirm') }}
                                    </flux:button>
                                    <flux:button wire:click="cancelAppointment({{ $appointment->id }})" size="xs" variant="ghost" class="justify-center">
                                        {{ __('Decline') }}
                                    </flux:button>
                                </div>
                            @endif
                        </div>
                    @empty
                        <div class="rounded-2xl border border-dashed border-zinc-200 p-6 text-center dark:border-zinc-800">
                            <div class="mx-auto flex size-11 items-center justify-center rounded-2xl bg-zinc-100 text-zinc-400 dark:bg-zinc-800 dark:text-zinc-500">
                                <flux:icon.inbox variant="mini" class="size-5" />
                            </div>
                            <p class="mt-3 text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ __('No upcoming bookings') }}</p>
                            <p class="mt-1 text-xs text-zinc-400">{{ __('New client requests will appear here.') }}</p>
                        </div>
                    @endforelse
                </div>
            </section>
        </aside>
    </div>

    <flux:modal wire:model="showAddSlotModal" class="max-w-xl">
        <div class="space-y-5">
            <div>
                <flux:heading size="lg">{{ __('Add availability slot') }}</flux:heading>
                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Create one slot or repeat it weekly for a predictable schedule.') }}</p>
            </div>

            <flux:field>
                <flux:label>{{ __('Date') }}</flux:label>
                <flux:input type="date" wire:model="selectedDate" min="{{ now()->toDateString() }}" />
                <flux:error name="selectedDate" />
            </flux:field>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <flux:field>
                    <flux:label>{{ __('Start time') }}</flux:label>
                    <flux:input type="time" wire:model="newSlotStartTime" />
                    <flux:error name="newSlotStartTime" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('End time') }}</flux:label>
                    <flux:input type="time" wire:model="newSlotEndTime" />
                    <flux:error name="newSlotEndTime" />
                </flux:field>
            </div>

            <flux:field>
                <flux:label>{{ __('Session duration') }}</flux:label>
                <flux:select wire:model="newSlotDuration">
                    <option value="30">{{ __('30 minutes') }}</option>
                    <option value="60">{{ __('60 minutes') }}</option>
                    <option value="90">{{ __('90 minutes') }}</option>
                    <option value="120">{{ __('2 hours') }}</option>
                </flux:select>
                <flux:error name="newSlotDuration" />
            </flux:field>

            <flux:field>
                <flux:checkbox wire:model="repeatWeekly" label="{{ __('Repeat weekly') }}" />
            </flux:field>

            @if ($repeatWeekly)
                <flux:field>
                    <flux:label>{{ __('For how many weeks?') }}</flux:label>
                    <flux:input type="number" wire:model="repeatWeeks" min="2" max="12" />
                    <flux:error name="repeatWeeks" />
                </flux:field>
            @endif

            <div class="flex flex-col gap-3 pt-2 sm:flex-row">
                <flux:button wire:click="addSlot" variant="primary" class="w-full sm:flex-1">
                    {{ __('Save slot') }}
                </flux:button>
                <flux:button wire:click="$set('showAddSlotModal', false)" variant="ghost" class="w-full sm:w-auto">
                    {{ __('Cancel') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</x-admin.page>
