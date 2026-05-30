@props(['sessionId'])

@php
    $sessionId = (int) $sessionId;
@endphp

<div {{ $attributes->merge(['class' => 'rounded-xl border border-amber-200 bg-amber-50/50 p-4 dark:border-amber-900/40 dark:bg-amber-950/20']) }}>
    <span class="text-xs font-semibold text-amber-800 dark:text-amber-300">{{ __('Attorney verification checklist') }}</span>
    <p class="mt-1 text-[11px] text-amber-700 dark:text-amber-400">{{ __('Complete all items before ending this party\'s session.') }}</p>
    <div class="mt-3 space-y-2">
        @foreach (config('docutrust.notary.verification_checklist', []) as $key)
            <label class="flex items-center gap-2.5 rounded-lg px-2 py-1.5 hover:bg-amber-100/50 dark:hover:bg-amber-950/30">
                <input
                    type="checkbox"
                    class="h-4 w-4 rounded border-amber-300 text-amber-600 focus:ring-amber-500 dark:border-amber-700"
                    wire:model.live="sessionChecklists.{{ $sessionId }}.{{ $key }}"
                />
                <span class="text-xs text-zinc-700 dark:text-zinc-300">{{ __(ucfirst(str_replace('_', ' ', $key))) }}</span>
            </label>
        @endforeach
    </div>
    <flux:error name="sessionChecklists.{{ $sessionId }}" />
    <div class="mt-4">
        <flux:button variant="primary" size="sm" type="button" wire:click="completeSession({{ $sessionId }})">{{ __('Complete session') }}</flux:button>
        <flux:error name="completeSession" />
    </div>
</div>
