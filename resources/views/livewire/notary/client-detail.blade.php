<?php

use App\Enums\NotaryRequestStatus;
use App\Models\NotaryClientNote;
use App\Models\NotaryRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    public User $clientUser;

    public string $newNote = '';

    public function mount(User $clientUser): void
    {
        $notaryId = Auth::id();
        abort_unless($notaryId !== null, 401);

        abort_unless(
            NotaryRequest::query()
                ->where('notary_user_id', $notaryId)
                ->where('user_id', $clientUser->id)
                ->exists(),
            404
        );

        $this->clientUser = $clientUser;
    }

    public function addNote(): void
    {
        $notaryId = Auth::id();
        abort_unless($notaryId !== null, 401);

        $validated = $this->validate([
            'newNote' => ['required', 'string', 'max:1000'],
        ]);

        NotaryClientNote::query()->create([
            'notary_user_id' => $notaryId,
            'client_user_id' => $this->clientUser->id,
            'note' => trim($validated['newNote']),
        ]);

        $this->reset('newNote');
        $this->resetValidation();
    }

    public function deleteNote(int $noteId): void
    {
        NotaryClientNote::query()
            ->where('notary_user_id', Auth::id())
            ->where('client_user_id', $this->clientUser->id)
            ->findOrFail($noteId)
            ->delete();
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        $notaryId = Auth::id();
        abort_unless($notaryId !== null, 401);

        $casesQuery = $this->casesQuery($notaryId);

        return [
            'cases' => $casesQuery->latest()->get(),
            'notes' => NotaryClientNote::query()
                ->where('notary_user_id', $notaryId)
                ->where('client_user_id', $this->clientUser->id)
                ->latest()
                ->get(),
            'totalCases' => $this->casesQuery($notaryId)->count(),
            'activeCases' => $this->casesQuery($notaryId)->whereNotIn('status', $this->closedStatusValues())->count(),
            'pendingCases' => $this->casesQuery($notaryId)
                ->whereIn('status', [
                    NotaryRequestStatus::Draft->value,
                    NotaryRequestStatus::Submitted->value,
                ])
                ->count(),
            'completedCases' => $this->casesQuery($notaryId)->where('status', NotaryRequestStatus::Notarized->value)->count(),
        ];
    }

    protected function casesQuery(int $notaryId): Builder
    {
        return NotaryRequest::query()
            ->where('notary_user_id', $notaryId)
            ->where('user_id', $this->clientUser->id);
    }

    /**
     * @return list<string>
     */
    protected function closedStatusValues(): array
    {
        return [
            NotaryRequestStatus::Notarized->value,
            NotaryRequestStatus::Rejected->value,
            NotaryRequestStatus::Cancelled->value,
            NotaryRequestStatus::Failed->value,
        ];
    }
}; ?>

@php
    $initial = Str::of($clientUser->name)->trim()->substr(0, 1)->upper();
@endphp

<x-admin.page gap="gap-6">
    <div class="space-y-5 border-b border-zinc-200/90 pb-5 dark:border-zinc-800">
        <a
            href="{{ route('notary.clients') }}"
            wire:navigate
            class="inline-flex items-center gap-1.5 text-sm font-medium text-zinc-500 transition hover:text-zinc-800 dark:text-zinc-400 dark:hover:text-zinc-200"
        >
            <flux:icon.arrow-left class="size-4" />
            {{ __('Back to clients') }}
        </a>

        <div class="flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex min-w-0 items-center gap-4">
                <div class="flex size-14 shrink-0 items-center justify-center rounded-full bg-teal-600 text-xl font-semibold text-white shadow-sm shadow-teal-600/20">
                    {{ $initial }}
                </div>
                <div class="min-w-0">
                    <h1 class="ui-page-heading truncate">{{ $clientUser->name }}</h1>
                    <div class="mt-1 flex flex-col gap-1 text-sm text-zinc-500 dark:text-zinc-400">
                        <span class="truncate">{{ $clientUser->email }}</span>
                        @if ($clientUser->mobile_number)
                            <span class="truncate">{{ $clientUser->mobile_number }}</span>
                        @endif
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-4 gap-2 rounded-2xl border border-zinc-200 bg-white p-3 text-center shadow-sm dark:border-zinc-800 dark:bg-zinc-900/60">
                <div class="min-w-16">
                    <p class="text-lg font-bold text-zinc-900 dark:text-zinc-100">{{ $totalCases }}</p>
                    <p class="text-[10px] font-semibold uppercase tracking-wide text-zinc-400">{{ __('Total') }}</p>
                </div>
                <div class="min-w-16">
                    <p class="text-lg font-bold text-amber-500">{{ $activeCases }}</p>
                    <p class="text-[10px] font-semibold uppercase tracking-wide text-zinc-400">{{ __('Active') }}</p>
                </div>
                <div class="min-w-16">
                    <p class="text-lg font-bold text-sky-500">{{ $pendingCases }}</p>
                    <p class="text-[10px] font-semibold uppercase tracking-wide text-zinc-400">{{ __('Pending') }}</p>
                </div>
                <div class="min-w-16">
                    <p class="text-lg font-bold text-emerald-500">{{ $completedCases }}</p>
                    <p class="text-[10px] font-semibold uppercase tracking-wide text-zinc-400">{{ __('Done') }}</p>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <section class="space-y-3 xl:col-span-2">
            <div>
                <h2 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">
                    {{ trans_choice('Case history (:count)|Case history (:count)', $cases->count(), ['count' => $cases->count()]) }}
                </h2>
                <p class="ui-muted mt-1 text-sm">{{ __('All notarization requests this client has submitted to you.') }}</p>
            </div>

            @forelse ($cases as $case)
                <a
                    href="{{ route('notary.requests.show', $case) }}"
                    wire:navigate
                    wire:key="notary-client-case-{{ $case->id }}"
                    class="ui-panel group flex flex-col gap-3 p-4 transition-colors hover:border-teal-400/80 sm:flex-row sm:items-center sm:justify-between dark:hover:border-teal-500/60"
                >
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold text-zinc-900 transition-colors group-hover:text-teal-700 dark:text-zinc-100 dark:group-hover:text-teal-300">
                            {{ $case->title }}
                        </p>
                        <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                            {{ str_replace('_', ' ', $case->request_type ?? __('Notarization')) }}
                            <span aria-hidden="true">&middot;</span>
                            {{ __('Updated :time', ['time' => $case->updated_at->diffForHumans()]) }}
                        </p>
                    </div>

                    <flux:badge size="sm" :color="$case->status->fluxColor()" class="self-start sm:self-center">
                        {{ $case->status->label() }}
                    </flux:badge>
                </a>
            @empty
                <div class="rounded-xl border border-dashed border-zinc-300/90 bg-zinc-50/80 px-6 py-10 text-center text-sm text-zinc-500 dark:border-zinc-700 dark:bg-zinc-900/40 dark:text-zinc-400">
                    {{ __('No cases yet') }}
                </div>
            @endforelse
        </section>

        <aside class="space-y-3">
            <div>
                <h2 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ __('Private notes') }}</h2>
                <p class="ui-muted mt-1 text-sm">{{ __('Only visible to you. Not shared with the client.') }}</p>
            </div>

            <form wire:submit="addNote" class="ui-panel space-y-3 p-4">
                <flux:field>
                    <flux:label>{{ __('Add note') }}</flux:label>
                    <flux:textarea
                        wire:model="newNote"
                        placeholder="{{ __('Add a note about this client...') }}"
                        rows="3"
                    />
                    <flux:error name="newNote" />
                </flux:field>

                <flux:button type="submit" size="sm" variant="primary" class="w-full">
                    {{ __('Save note') }}
                </flux:button>
            </form>

            <div class="space-y-3">
                @forelse ($notes as $note)
                    <div wire:key="notary-client-note-{{ $note->id }}" class="ui-panel p-4">
                        <p class="whitespace-pre-line text-sm text-zinc-700 dark:text-zinc-300">{{ $note->note }}</p>
                        <div class="mt-3 flex items-center justify-between gap-3">
                            <p class="text-xs text-zinc-400">{{ $note->created_at->diffForHumans() }}</p>
                            <flux:button
                                type="button"
                                size="sm"
                                variant="ghost"
                                wire:click="deleteNote({{ $note->id }})"
                                wire:confirm="{{ __('Delete this note?') }}"
                            >
                                {{ __('Delete') }}
                            </flux:button>
                        </div>
                    </div>
                @empty
                    <p class="rounded-xl border border-dashed border-zinc-300/90 bg-zinc-50/80 px-6 py-6 text-center text-sm text-zinc-500 dark:border-zinc-700 dark:bg-zinc-900/40 dark:text-zinc-400">
                        {{ __('No notes yet') }}
                    </p>
                @endforelse
            </div>
        </aside>
    </div>
</x-admin.page>
