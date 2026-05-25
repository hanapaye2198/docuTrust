@props([
    'user' => auth()->user(),
])

@if ($user && $user->mobile_number)
    @if ($user->mobile_verified_at)
        <div {{ $attributes->merge(['class' => 'flex items-center gap-2 rounded-xl border border-emerald-200/80 bg-emerald-50/80 px-3 py-2 text-xs font-medium text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300']) }}>
            <flux:icon.check-badge variant="mini" class="size-4 shrink-0" />
            <span>{{ __('Mobile verified') }}</span>
        </div>
    @else
        <div {{ $attributes->merge(['class' => 'flex flex-col gap-2 rounded-xl border border-amber-200/80 bg-amber-50/80 px-3 py-2 text-xs text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200']) }}>
            <div class="flex items-center gap-2 font-medium">
                <flux:icon.exclamation-triangle variant="mini" class="size-4 shrink-0" />
                <span>{{ __('Mobile not verified') }}</span>
            </div>
            <flux:button
                :href="route('onboarding.mobile')"
                variant="ghost"
                size="sm"
                class="w-full justify-center"
                wire:navigate
            >
                {{ __('Verify now') }}
            </flux:button>
        </div>
    @endif
@endif
