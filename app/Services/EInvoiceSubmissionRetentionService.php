<?php

namespace App\Services;

use App\Models\EInvoiceSubmission;
use Illuminate\Database\Eloquent\Collection;

class EInvoiceSubmissionRetentionService
{
    public function pruneResolvedPayloads(int $olderThanDays = 30, int $limit = 500): int
    {
        $cutoff = now()->subDays(max(1, $olderThanDays));

        /** @var Collection<int, EInvoiceSubmission> $submissions */
        $submissions = EInvoiceSubmission::query()
            ->whereNull('payload_pruned_at')
            ->whereNotNull('resolved_at')
            ->where('resolved_at', '<=', $cutoff)
            ->where(function ($query): void {
                $query
                    ->whereNotNull('request_payload')
                    ->orWhereNotNull('response_payload');
            })
            ->orderBy('resolved_at')
            ->limit(max(1, $limit))
            ->get();

        foreach ($submissions as $submission) {
            $submission->forceFill([
                'request_payload' => null,
                'response_payload' => null,
                'payload_pruned_at' => now(),
            ])->save();
        }

        return $submissions->count();
    }
}
