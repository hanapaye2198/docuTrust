<div @if ($this->shouldPoll()) wire:poll.3s="checkStatus" @endif>
    @if ($authStatus === 'authorized')
        <span class="inline-flex items-center rounded-full border border-emerald-200 bg-emerald-100 px-3 py-1.5 text-xs font-semibold uppercase tracking-[0.12em] text-emerald-800 dark:border-emerald-900/50 dark:bg-emerald-950/50 dark:text-emerald-200">
            {{ __('Authorized - ready to sign') }}
        </span>
    @elseif ($authStatus === 'expired')
        <span class="inline-flex items-center rounded-full border border-red-200 bg-red-100 px-3 py-1.5 text-xs font-semibold uppercase tracking-[0.12em] text-red-800 dark:border-red-900/50 dark:bg-red-950/50 dark:text-red-200">
            {{ __('Authorization expired - please re-authorize') }}
        </span>
    @else
        <span class="inline-flex items-center rounded-full border border-amber-200 bg-amber-100 px-3 py-1.5 text-xs font-semibold uppercase tracking-[0.12em] text-amber-800 dark:border-amber-900/50 dark:bg-amber-950/50 dark:text-amber-200">
            {{ __('Awaiting authorization...') }}
        </span>
    @endif
</div>
