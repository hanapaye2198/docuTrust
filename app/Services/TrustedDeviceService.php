<?php

namespace App\Services;

use App\Models\TrustedDevice;
use App\Models\User;
use Illuminate\Http\Request;

class TrustedDeviceService
{
    public function isTrusted(User $user, Request $request): bool
    {
        $fingerprint = $this->fingerprint($request);

        $device = $user->trustedDevices()
            ->where('device_fingerprint', $fingerprint)
            ->whereNull('revoked_at')
            ->where('expires_at', '>=', now())
            ->first();

        if ($device === null) {
            return false;
        }

        $device->forceFill([
            'last_used_at' => now(),
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 65535),
        ])->save();

        return true;
    }

    public function trustCurrentDevice(User $user, Request $request, int $days = 30): TrustedDevice
    {
        $fingerprint = $this->fingerprint($request);

        return TrustedDevice::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'device_fingerprint' => $fingerprint,
            ],
            [
                'device_name' => $this->deviceName($request),
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 65535),
                'last_used_at' => now(),
                'expires_at' => now()->addDays($days),
                'revoked_at' => null,
            ],
        );
    }

    public function revoke(User $user, int $deviceId): void
    {
        $device = $user->trustedDevices()->whereKey($deviceId)->first();
        if ($device === null) {
            return;
        }

        $device->forceFill([
            'revoked_at' => now(),
        ])->save();
    }

    public function fingerprint(Request $request): string
    {
        return hash('sha256', implode('|', [
            (string) $request->ip(),
            (string) $request->header('User-Agent', ''),
            (string) $request->header('Accept-Language', ''),
        ]));
    }

    public function deviceName(Request $request): string
    {
        $userAgent = trim((string) $request->userAgent());
        if ($userAgent === '') {
            return 'Unknown device';
        }

        return substr($userAgent, 0, 120);
    }
}
