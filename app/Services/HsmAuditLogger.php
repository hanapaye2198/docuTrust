<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * HSM Audit Logger
 * 
 * Logs all HSM operations for CSC compliance audit trail requirements.
 */
class HsmAuditLogger
{
    public function logOperation(
        string $operation,
        ?string $keyId,
        ?string $objectType = null,
        ?int $objectId = null,
        ?string $userId = null,
        ?string $ipAddress = null,
        array $details = []
    ): void {
        $logEntry = [
            'operation' => $operation,
            'key_id' => $keyId,
            'object_type' => $objectType,
            'object_id' => $objectId,
            'user_id' => $userId,
            'ip_address' => $ipAddress,
            'details' => $details,
            'timestamp' => now()->toIso8601String(),
        ];

        // Log to database
        \DB::table('hsm_key_audit_log')->insert($logEntry);

        // Log to file
        Log::channel('hsm_audit')->info('HSM operation', $logEntry);
    }

    public function logKeyGeneration(
        string $keyId,
        ?string $objectType = null,
        ?int $objectId = null,
        ?string $userId = null
    ): void {
        $this->logOperation(
            'key_generate',
            $keyId,
            $objectType,
            $objectId,
            $userId,
            request()->ip(),
            ['key_size' => config('docutrust.pki.key_size', 2048)]
        );
    }

    public function logKeySign(
        string $keyId,
        string $hash,
        ?string $objectType = null,
        ?int $objectId = null,
        ?string $userId = null
    ): void {
        $this->logOperation(
            'key_sign',
            $keyId,
            $objectType,
            $objectId,
            $userId,
            request()->ip(),
            ['hash_algorithm' => 'SHA-256', 'hash' => $hash]
        );
    }

    public function logKeyVerify(
        string $keyId,
        string $hash,
        bool $success,
        ?string $objectType = null,
        ?int $objectId = null,
        ?string $userId = null
    ): void {
        $this->logOperation(
            'key_verify',
            $keyId,
            $objectType,
            $objectId,
            $userId,
            request()->ip(),
            [
                'hash_algorithm' => 'SHA-256',
                'hash' => $hash,
                'result' => $success ? 'success' : 'failure',
            ]
        );
    }

    public function logKeyDestruction(
        string $keyId,
        ?string $objectType = null,
        ?int $objectId = null,
        ?string $userId = null
    ): void {
        $this->logOperation(
            'key_destroy',
            $keyId,
            $objectType,
            $objectId,
            $userId,
            request()->ip()
        );
    }

    /**
     * Get audit report for specific period
     *
     * @param \DateTimeInterface $startDate
     * @param \DateTimeInterface $endDate
     * @return array
     */
    public function getAuditReport(\DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        $records = \DB::table('hsm_key_audit_log')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->orderBy('created_at', 'desc')
            ->get();

        return [
            'start_date' => $startDate->toIso8601String(),
            'end_date' => $endDate->toIso8601String(),
            'total_operations' => $records->count(),
            'operations_by_type' => $this->groupByOperation($records),
            'records' => $records->toArray(),
        ];
    }

    private function groupByOperation(\Illuminate\Support\Collection $records): array
    {
        $groups = [];

        foreach ($records as $record) {
            $op = $record->operation;
            $groups[$op] = ($groups[$op] ?? 0) + 1;
        }

        return $groups;
    }
}
