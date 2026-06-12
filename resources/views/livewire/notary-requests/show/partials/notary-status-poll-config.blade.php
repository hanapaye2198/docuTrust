@if (! in_array($notaryRequest->status->value, ['notarized', 'rejected', 'failed', 'cancelled']))
    @php
        $latestPollingPayment = $notaryRequest->payments
            ->sortByDesc(fn ($payment) => $payment->created_at?->getTimestamp() ?? 0)
            ->first();
    @endphp
    <script id="notary-status-config" type="application/json">
        {!! json_encode([
            'requestId' => $notaryRequest->id,
            'channel' => 'notary-request.' . $notaryRequest->id,
            'statusUrl' => '/api/notary-requests/' . $notaryRequest->id . '/status',
            'interval' => 5000,
            'currentStatus' => $notaryRequest->status->value,
            'latestPayment' => $latestPollingPayment ? [
                'id' => $latestPollingPayment->id,
                'status' => $latestPollingPayment->status->value,
                'paid_at' => $latestPollingPayment->paid_at?->toIso8601String(),
                'expires_at' => $latestPollingPayment->expires_at?->toIso8601String(),
                'last_verified_at' => $latestPollingPayment->last_verified_at?->toIso8601String(),
                'updated_at' => $latestPollingPayment->updated_at?->toIso8601String(),
            ] : null,
        ]) !!}
    </script>
@endif
