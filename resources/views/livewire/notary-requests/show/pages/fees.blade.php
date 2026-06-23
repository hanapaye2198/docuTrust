@if ($panels['closing'])
    <div
        wire:key="case-page-fees"
        x-data
        x-init="$nextTick(() => {
            const scrollArea = document.querySelector('.main-scroll-area');
            if (scrollArea) {
                scrollArea.scrollTop = 0;
            }
        })"
    >
        @include('livewire.notary-requests.show.partials.tab-closing')
    </div>
@else
    <div class="ui-panel p-6 text-sm text-zinc-600 dark:text-zinc-400">
        {{ __('Fees and register actions are not available for this case yet.') }}
    </div>
@endif
