@if ($panels['audit'])
    @include('livewire.notary-requests.show.partials.tab-audit')
@else
    <div class="ui-panel p-6 text-sm text-zinc-600 dark:text-zinc-400">
        {{ __('Case history appears after audit events, journal entries, or finalization issues exist.') }}
    </div>
@endif
