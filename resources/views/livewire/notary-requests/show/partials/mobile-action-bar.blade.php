@php
    $primaryAction = $primaryAction ?? null;
@endphp

@if ($primaryAction)
    <div class="fixed inset-x-0 bottom-0 z-40 border-t border-zinc-200 bg-white/95 p-3 shadow-[0_-8px_30px_rgba(0,0,0,0.08)] backdrop-blur-sm dark:border-zinc-800 dark:bg-zinc-950/95 xl:hidden">
        <div class="mx-auto flex max-w-3xl flex-col gap-2">
            <p class="text-center text-xs font-medium text-zinc-600 dark:text-zinc-400">
                {{ __('Next') }}: {{ $primaryAction['label'] }}
            </p>
            @include('livewire.notary-requests.show.partials.primary-action-button', [
                'action' => $primaryAction,
                'size' => 'base',
                'class' => 'w-full min-h-12 text-base',
            ])
        </div>
    </div>
@endif
