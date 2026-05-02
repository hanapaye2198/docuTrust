<?php

use App\Models\Tag;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    #[Url]
    public string $search = '';

    #[Url]
    public string $statusFilter = 'all';

    #[Url]
    public ?int $selectedTagId = null;

    public bool $creatingTag = false;

    public string $newTagName = '';

    public function startCreateTag(): void
    {
        $this->authorize('create', Tag::class);
        $this->creatingTag = true;
        $this->newTagName = '';
        $this->resetErrorBag();
    }

    public function cancelCreateTag(): void
    {
        $this->creatingTag = false;
        $this->newTagName = '';
    }

    public function saveNewTag(): void
    {
        $this->authorize('create', Tag::class);
        $this->validate([
            'newTagName' => [
                'required',
                'string',
                'max:64',
                Rule::unique('tags', 'name')->where('user_id', Auth::id()),
            ],
        ]);

        Auth::user()->tags()->create(['name' => trim($this->newTagName)]);
        $this->creatingTag = false;
        $this->newTagName = '';
    }

    public function selectAllTemplates(): void
    {
        $this->statusFilter = 'all';
        $this->selectedTagId = null;
    }

    public function selectStatus(string $status): void
    {
        if (! in_array($status, ['draft', 'ready'], true)) {
            return;
        }
        $this->statusFilter = $status;
    }

    public function selectTag(?int $tagId): void
    {
        if ($tagId !== null) {
            $exists = Auth::user()->tags()->whereKey($tagId)->exists();
            if (! $exists) {
                $this->selectedTagId = null;

                return;
            }
        }
        $this->selectedTagId = $tagId;
    }

    public function with(): array
    {
        $tags = Auth::user()->tags()->orderBy('name')->get();

        $query = Auth::user()->templates()->with(['tags', 'templateFields']);

        if ($this->statusFilter === 'draft') {
            $query->whereDoesntHave('templateFields');
        } elseif ($this->statusFilter === 'ready') {
            $query->whereHas('templateFields');
        }

        if ($this->selectedTagId !== null) {
            $query->whereHas('tags', fn ($q) => $q->where('tags.id', $this->selectedTagId));
        }

        if ($this->search !== '') {
            $query->where('name', 'like', '%'.$this->search.'%');
        }

        $templates = $query->latest()->get();

        return [
            'sidebarTags' => $tags,
            'templates' => $templates,
            'hasAnyTemplates' => Auth::user()->templates()->exists(),
        ];
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6 p-1">
    {{-- Title row: directly under breadcrumb, efficient use of width --}}
    <div
        class="flex flex-col gap-3 border-b border-zinc-200/90 pb-4 sm:flex-row sm:items-center sm:justify-between dark:border-zinc-800"
    >
        <div class="min-w-0">
            <h1 class="text-2xl font-semibold tracking-tight text-zinc-900 dark:text-zinc-50 md:text-3xl">
                {{ __('Templates') }}
            </h1>
            <p class="ui-muted mt-1 text-base">{{ __('Create once, reuse for every document.') }}</p>
        </div>
        <flux:button
            variant="primary"
            class="shrink-0 self-start sm:self-center"
            :href="route('templates.create')"
            wire:navigate
        >
            {{ __('Create template') }}
        </flux:button>
    </div>

    <div class="flex w-full flex-col gap-4 lg:flex-row lg:items-start lg:gap-6">
        {{-- Filters: closer to main sidebar (tighter gap), compact card --}}
        <aside class="w-full shrink-0 lg:w-52 xl:w-56">
            <div
                class="rounded-xl border border-zinc-200/90 bg-white p-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-900/70"
            >
                <h2 class="text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                    {{ __('Library') }}
                </h2>

                <flux:button class="mt-3 w-full" size="sm" variant="outline" :href="route('templates.create')" wire:navigate>
                    + {{ __('Create new') }}
                </flux:button>

                <div class="mt-4 border-t border-zinc-200 pt-4 dark:border-zinc-700">
                    @if (! $creatingTag)
                        <button
                            type="button"
                            wire:click="startCreateTag"
                            class="flex w-full items-center justify-between rounded-lg border border-zinc-200 bg-zinc-50/80 px-2.5 py-2 text-left text-sm font-medium text-zinc-800 transition hover:border-teal-400 hover:bg-teal-50/50 dark:border-zinc-600 dark:bg-zinc-800/50 dark:text-zinc-100 dark:hover:border-teal-500"
                        >
                            {{ __('Create new tag') }}
                            <flux:icon.chevron-right class="size-4 shrink-0 text-zinc-400" />
                        </button>
                    @else
                        <div class="space-y-2 rounded-lg border border-teal-200 bg-teal-50/50 p-2.5 dark:border-teal-900/40 dark:bg-teal-950/30">
                            <flux:input wire:model="newTagName" type="text" placeholder="{{ __('Tag name') }}" wire:keydown.enter="saveNewTag" />
                            <flux:error name="newTagName" />
                            <div class="flex gap-2">
                                <flux:button size="sm" variant="primary" type="button" wire:click="saveNewTag">{{ __('Save') }}</flux:button>
                                <flux:button size="sm" variant="ghost" type="button" wire:click="cancelCreateTag">{{ __('Cancel') }}</flux:button>
                            </div>
                        </div>
                    @endif
                </div>

                <nav class="mt-4 space-y-0.5 border-t border-zinc-200 pt-4 dark:border-zinc-700" aria-label="{{ __('Template filters') }}">
                    <button
                        type="button"
                        wire:click="selectAllTemplates"
                        @class([
                            'flex w-full rounded-md px-2.5 py-1.5 text-left text-sm transition',
                            'bg-teal-600 font-semibold text-white shadow-sm dark:bg-teal-500' => $selectedTagId === null && $statusFilter === 'all',
                            'text-zinc-700 hover:bg-zinc-100 dark:text-zinc-200 dark:hover:bg-zinc-800' => ! ($selectedTagId === null && $statusFilter === 'all'),
                        ])
                    >
                        {{ __('All templates') }}
                    </button>
                    <button
                        type="button"
                        wire:click="selectStatus('draft')"
                        @class([
                            'flex w-full rounded-md px-2.5 py-1.5 text-left text-sm transition',
                            'bg-teal-600 font-semibold text-white shadow-sm dark:bg-teal-500' => $statusFilter === 'draft',
                            'text-zinc-700 hover:bg-zinc-100 dark:text-zinc-200 dark:hover:bg-zinc-800' => $statusFilter !== 'draft',
                        ])
                    >
                        {{ __('Drafts') }}
                    </button>
                    <button
                        type="button"
                        wire:click="selectStatus('ready')"
                        @class([
                            'flex w-full rounded-md px-2.5 py-1.5 text-left text-sm transition',
                            'bg-teal-600 font-semibold text-white shadow-sm dark:bg-teal-500' => $statusFilter === 'ready',
                            'text-zinc-700 hover:bg-zinc-100 dark:text-zinc-200 dark:hover:bg-zinc-800' => $statusFilter !== 'ready',
                        ])
                    >
                        {{ __('Ready') }}
                    </button>

                    @foreach ($sidebarTags as $tag)
                        <button
                            type="button"
                            wire:click="selectTag({{ $tag->id }})"
                            wire:key="tag-{{ $tag->id }}"
                            @class([
                                'flex w-full rounded-md px-2.5 py-1.5 text-left text-sm transition',
                                'bg-teal-600 font-semibold text-white shadow-sm dark:bg-teal-500' => $selectedTagId === $tag->id,
                                'text-zinc-700 hover:bg-zinc-100 dark:text-zinc-200 dark:hover:bg-zinc-800' => $selectedTagId !== $tag->id,
                            ])
                        >
                            {{ $tag->name }}
                        </button>
                    @endforeach
                </nav>
            </div>
        </aside>

        {{-- Main list: maximum width for table --}}
        <div class="min-w-0 w-full flex-1">
            <div class="mb-3">
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    type="search"
                    placeholder="{{ __('Search templates…') }}"
                    class="w-full"
                />
            </div>

            <div class="ui-panel overflow-hidden p-0">
                @if ($templates->isEmpty())
                    <div class="px-5 py-12 text-center">
                        <p class="text-sm text-zinc-500 dark:text-zinc-400">
                            @if (! $hasAnyTemplates)
                                {{ __('No templates yet.') }}
                            @else
                                {{ __('No templates match your filters.') }}
                            @endif
                        </p>
                        <flux:button class="mt-3" variant="primary" :href="route('templates.create')" wire:navigate>
                            {{ __('Create template') }}
                        </flux:button>
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[640px] text-left text-sm">
                            <thead class="border-b border-zinc-200 bg-zinc-50/90 dark:border-zinc-800 dark:bg-zinc-900/60">
                                <tr>
                                    <th class="px-4 py-2.5 text-xs font-semibold uppercase tracking-wider text-zinc-600 dark:text-zinc-400">
                                        {{ __('Name') }}
                                    </th>
                                    <th class="hidden px-4 py-2.5 text-xs font-semibold uppercase tracking-wider text-zinc-600 sm:table-cell dark:text-zinc-400">
                                        {{ __('Tags') }}
                                    </th>
                                    <th class="hidden px-4 py-2.5 text-xs font-semibold uppercase tracking-wider text-zinc-600 md:table-cell dark:text-zinc-400">
                                        {{ __('Created') }}
                                    </th>
                                    <th class="px-4 py-2.5 text-right text-xs font-semibold uppercase tracking-wider text-zinc-600 dark:text-zinc-400">
                                        {{ __('Actions') }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                                @foreach ($templates as $template)
                                    <tr
                                        class="transition-colors hover:bg-teal-500/[0.04] dark:hover:bg-white/[0.03]"
                                        wire:key="tpl-{{ $template->id }}"
                                    >
                                        <td class="px-4 py-2.5 font-medium text-zinc-900 dark:text-zinc-100">
                                            {{ $template->name }}
                                        </td>
                                        <td class="hidden px-4 py-2.5 sm:table-cell">
                                            <div class="flex flex-wrap gap-1">
                                                @forelse ($template->tags as $tag)
                                                    <span
                                                        class="inline-flex rounded-md bg-zinc-100 px-1.5 py-0.5 text-xs font-medium text-zinc-700 dark:bg-zinc-800 dark:text-zinc-200"
                                                    >
                                                        {{ $tag->name }}
                                                    </span>
                                                @empty
                                                    <span class="text-xs text-zinc-400">—</span>
                                                @endforelse
                                            </div>
                                        </td>
                                        <td class="hidden px-4 py-2.5 text-zinc-600 md:table-cell dark:text-zinc-400">
                                            {{ $template->created_at->format('M j, Y') }}
                                        </td>
                                        <td class="px-4 py-2 text-right">
                                            <div class="flex flex-wrap items-center justify-end gap-1.5">
                                                <flux:button size="sm" variant="primary" :href="route('templates.use', $template)" wire:navigate>
                                                    {{ __('Use template') }}
                                                </flux:button>
                                                <flux:button size="sm" variant="ghost" :href="route('templates.edit', $template)" wire:navigate>
                                                    {{ __('Edit') }}
                                                </flux:button>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
