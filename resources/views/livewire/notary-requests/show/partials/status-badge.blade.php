<flux:badge
    size="sm"
    :color="$notaryRequest->status->fluxColor()"
    data-notary-status-badge="{{ $notaryRequest->status->value }}"
>
    {{ $notaryRequest->status->label() }}
</flux:badge>
