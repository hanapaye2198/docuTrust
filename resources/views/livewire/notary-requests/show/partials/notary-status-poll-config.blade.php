@if (! in_array($notaryRequest->status->value, ['notarized', 'rejected', 'failed', 'cancelled']))
    <script id="notary-status-config" type="application/json">
        {!! json_encode([
            'requestId' => $notaryRequest->id,
            'statusUrl' => '/api/notary-requests/' . $notaryRequest->id . '/status',
            'interval' => 5000,
            'currentStatus' => $notaryRequest->status->value,
        ]) !!}
    </script>
@endif
