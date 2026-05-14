<?php

namespace App\Services;

use App\Contracts\HsmService;
use Illuminate\Support\Facades\Log;

/**
 * HSM Health Monitor
 * 
 * Monitors HSM health and provides alerts for CSC compliance requirements.
 * Implements redundant monitoring as required by CSC standards.
 */
class HsmHealthMonitor
{
    private int $checkInterval = 60; // seconds

    public function __construct(private readonly HsmService $hsmService) {}

    /**
     * Perform health check and log results
     *
     * @return array{status: string, details: array}
     */
    public function check(): array
    {
        $status = $this->hsmService->getStatus();
        $slotInfo = $this->hsmService->getSlotInfo();

        $health = [
            'status' => $this->determineHealthStatus($status),
            'timestamp' => now()->toIso8601String(),
            'details' => [
                'hsm_status' => $status,
                'slot_info' => $slotInfo,
                'uptime_seconds' => $status['uptime'] ?? 0,
                'error_count' => $status['errors'] ?? 0,
            ],
        ];

        $this->logHealthCheck($health);

        return $health;
    }

    /**
     * Check if HSM is healthy enough for PKI operations
     *
     * @return bool
     */
    public function isOperational(): bool
    {
        $status = $this->hsmService->getStatus();

        return $status['status'] === 'online' && $status['errors'] === 0;
    }

    /**
     * Get detailed health report for audit purposes
     *
     * @return array
     */
    public function getAuditReport(): array
    {
        $status = $this->hsmService->getStatus();
        $slotInfo = $this->hsmService->getSlotInfo();

        return [
            'report_generated' => now()->toIso8601String(),
            'hsm_model' => $slotInfo['model'] ?? 'Unknown',
            'firmware_version' => $slotInfo['firmwareVersion'] ?? 'Unknown',
            'status' => $status['status'],
            'uptime_seconds' => $status['uptime'] ?? 0,
            'error_count' => $status['errors'] ?? 0,
            'last_check' => $status['lastCheck'] ?? null,
            'redundancy_status' => $this->checkRedundancy(),
        ];
    }

    private function determineHealthStatus(array $status): string
    {
        if ($status['status'] === 'offline') {
            return 'unhealthy';
        }

        if ($status['errors'] > 0) {
            return 'degraded';
        }

        return 'healthy';
    }

    private function logHealthCheck(array $health): void
    {
        $level = match ($health['status']) {
            'unhealthy' => 'error',
            'degraded' => 'warning',
            default => 'info',
        };

        Log::channel('hsm_health')->log($level, 'HSM health check', $health);
    }

    private function checkRedundancy(): array
    {
        $slotInfo = $this->hsmService->getSlotInfo();
        $status = $this->hsmService->getStatus();

        return [
            'slots_available' => $slotInfo['availableSlots'] ?? 0,
            'slots_total' => $slotInfo['slotCount'] ?? 0,
            'redundant' => ($slotInfo['availableSlots'] ?? 0) > 0,
            'failover_capable' => $status['status'] !== 'offline',
        ];
    }
}
