@if ($notaryRequest->status === \App\Enums\NotaryRequestStatus::Notarized)
    @include('livewire.notary-requests.show.partials.section-completed')
@endif

@include('livewire.notary-requests.show.partials.tab-documents')
