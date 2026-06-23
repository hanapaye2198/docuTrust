<div class="grid items-start gap-5 xl:grid-cols-[minmax(0,1fr)_minmax(280px,400px)]">
    <div class="min-w-0">
        @if ($panels['session'])
            @include('livewire.notary-requests.show.partials.tab-session')
        @endif
    </div>

    <div class="min-w-0 xl:sticky xl:top-4">
        @include('livewire.notary-requests.show.partials.tab-parties')
    </div>
</div>
