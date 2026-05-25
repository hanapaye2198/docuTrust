<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;

class OtpAuditLogger
{
    public function log(
        User $user,
        string $action,
        ?string $mobileNumber = null,
        ?Request $request = null,
    ): void {
        app(OnboardingAuditLogger::class)->log($user, $action, $request);

        if ($mobileNumber !== null && $mobileNumber !== '') {
            logger()->channel('audit')->info('otp_event', [
                'user_id' => $user->id,
                'action' => $action,
                'mobile_number' => $this->maskMobile($mobileNumber),
                'ip_address' => ($request ?? request())->ip(),
                'user_agent' => ($request ?? request())->userAgent(),
                'timestamp' => now()->toIso8601String(),
            ]);
        }
    }

    private function maskMobile(string $mobileNumber): string
    {
        $digits = preg_replace('/\D+/', '', $mobileNumber) ?? $mobileNumber;
        $length = strlen($digits);

        if ($length <= 4) {
            return str_repeat('*', $length);
        }

        return str_repeat('*', max($length - 4, 0)).substr($digits, -4);
    }
}
