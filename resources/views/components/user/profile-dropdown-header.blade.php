@props(['user'])

@php
    $summary = app(\App\Services\TrustProfile\TrustProfileService::class)->summary($user);
@endphp

<div class="flex items-center gap-3 px-2 py-2.5 text-left">
    @if ($user->profile_photo_path)
        <img
            src="{{ route('settings.trust-profile.photo') }}"
            alt=""
            class="size-9 shrink-0 rounded-lg object-cover ring-1 ring-zinc-200 dark:ring-zinc-700"
        />
    @else
        <span class="relative flex size-9 shrink-0 overflow-hidden rounded-lg">
            <span class="flex size-full items-center justify-center rounded-lg bg-gradient-to-br from-teal-500 to-emerald-600 text-sm font-bold text-white">
                {{ $user->initials() }}
            </span>
        </span>
    @endif
    <div class="grid min-w-0 flex-1 text-left leading-tight">
        <span class="truncate text-sm font-semibold text-zinc-900 dark:text-zinc-100">{{ $user->buildFullName() ?: $user->name }}</span>
        <span class="truncate text-xs text-zinc-500 dark:text-zinc-400">{{ $summary['role_label'] }}</span>
        <div class="mt-1 flex flex-wrap items-center gap-1.5">
            <flux:badge size="sm" variant="outline">{{ $summary['badge_label'] }}</flux:badge>
            <span class="text-[10px] font-medium text-zinc-500 dark:text-zinc-400">
                {{ __('Trust :score', ['score' => $summary['trust_score']]) }}
            </span>
        </div>
    </div>
</div>
