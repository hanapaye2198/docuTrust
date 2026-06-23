<?php

use App\Enums\UserRole;
use App\Http\Requests\StoreNotaryAppointmentRequest;
use App\Models\NotaryAppointment;
use App\Models\NotaryAvailabilitySlot;
use App\Models\NotaryRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    public User $notaryUser;

    public ?int $selectedSlotId = null;

    public ?int $notaryRequestId = null;

    public string $notes = '';

    public function mount(User $notaryUser): void
    {
        abort_unless($notaryUser->role === UserRole::Notary, 404);

        $this->notaryUser = $notaryUser;
    }

    public function book(): void
    {
        $client = Auth::user();
        abort_unless($client !== null, 403);

        $validated = $this->validate(StoreNotaryAppointmentRequest::livewireRules());

        DB::transaction(function () use ($client, $validated): void {
            $slot = NotaryAvailabilitySlot::query()
                ->where('notary_user_id', $this->notaryUser->id)
                ->where('is_booked', false)
                ->where('is_blocked', false)
                ->where('date', '>=', today())
                ->whereKey($validated['selectedSlotId'])
                ->lockForUpdate()
                ->firstOrFail();

            $notaryRequestId = $validated['notaryRequestId'] ?? null;

            if ($notaryRequestId !== null) {
                NotaryRequest::query()
                    ->whereKey($notaryRequestId)
                    ->where('user_id', $client->id)
                    ->where('notary_user_id', $this->notaryUser->id)
                    ->firstOrFail();
            }

            NotaryAppointment::query()->create([
                'notary_availability_slot_id' => $slot->id,
                'notary_request_id' => $notaryRequestId,
                'client_user_id' => $client->id,
                'notary_user_id' => $this->notaryUser->id,
                'status' => 'pending',
                'notes' => trim($validated['notes'] ?? '') !== '' ? trim($validated['notes']) : null,
            ]);

            $slot->update(['is_booked' => true]);
        });

        session()->flash('success', __('Appointment requested. Your attorney will confirm shortly.'));
        $this->redirect(route('notary-requests.index'), navigate: true);
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        $client = Auth::user();
        abort_unless($client !== null, 403);

        return [
            'availableSlots' => NotaryAvailabilitySlot::query()
                ->where('notary_user_id', $this->notaryUser->id)
                ->where('is_booked', false)
                ->where('is_blocked', false)
                ->where('date', '>=', today())
                ->orderBy('date')
                ->orderBy('start_time')
                ->get()
                ->groupBy(fn (NotaryAvailabilitySlot $slot) => $slot->date->format('Y-m-d')),
            'clientRequests' => NotaryRequest::query()
                ->where('user_id', $client->id)
                ->where('notary_user_id', $this->notaryUser->id)
                ->whereDoesntHave('notaryAppointment', fn (Builder $query) => $query->whereIn('status', ['pending', 'confirmed']))
                ->latest()
                ->limit(10)
                ->get(),
        ];
    }
}; ?>

@php
    /** @var Collection<string, Collection<int, NotaryAvailabilitySlot>> $availableSlots */
    /** @var Collection<int, NotaryRequest> $clientRequests */
@endphp

<x-admin.page gap="gap-6">
    <div class="mx-auto max-w-2xl space-y-6 py-4">
        <div>
            <h1 class="text-xl font-semibold text-zinc-900 dark:text-white">{{ __('Book an appointment') }}</h1>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                {{ __('with Atty. :name', ['name' => $notaryUser->name]) }}
            </p>
        </div>

        @if (session('success'))
            <flux:callout variant="success" icon="check-circle">
                <flux:callout.text>{{ session('success') }}</flux:callout.text>
            </flux:callout>
        @endif

        <section class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700/60 dark:bg-zinc-900/70">
            <div class="space-y-5">
                @forelse ($availableSlots as $dateStr => $slots)
                    <div wire:key="booking-date-{{ $dateStr }}">
                        <p class="mb-2 text-sm font-semibold text-zinc-700 dark:text-zinc-300">
                            {{ Carbon::parse($dateStr)->format('l, F j, Y') }}
                        </p>

                        <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                            @foreach ($slots as $slot)
                                <flux:button
                                    wire:key="booking-slot-{{ $slot->id }}"
                                    wire:click="$set('selectedSlotId', {{ $slot->id }})"
                                    variant="{{ $selectedSlotId === $slot->id ? 'primary' : 'outline' }}"
                                    class="justify-center"
                                >
                                    {{ Carbon::parse($slot->start_time)->format('g:i A') }}
                                </flux:button>
                            @endforeach
                        </div>
                    </div>
                @empty
                    <div class="py-12 text-center text-sm text-zinc-400">
                        {{ __('No available slots at the moment. Please check back later.') }}
                    </div>
                @endforelse
            </div>
        </section>

        @if ($selectedSlotId)
            <section class="space-y-4 rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700/60 dark:bg-zinc-900/70">
                @if ($clientRequests->isNotEmpty())
                    <flux:field>
                        <flux:label>{{ __('Related notary case') }}</flux:label>
                        <flux:select wire:model="notaryRequestId">
                            <option value="">{{ __('No case selected') }}</option>
                            @foreach ($clientRequests as $request)
                                <option value="{{ $request->id }}">{{ $request->title }}</option>
                            @endforeach
                        </flux:select>
                        <flux:error name="notaryRequestId" />
                    </flux:field>
                @endif

                <flux:field>
                    <flux:label>{{ __('Notes for your attorney') }}</flux:label>
                    <flux:textarea
                        wire:model="notes"
                        placeholder="{{ __('Briefly describe the document or notarial act needed...') }}"
                        rows="3"
                    />
                    <flux:error name="notes" />
                </flux:field>

                <flux:button wire:click="book" variant="primary" class="w-full">
                    {{ __('Request appointment') }}
                </flux:button>
            </section>
        @endif
    </div>
</x-admin.page>
