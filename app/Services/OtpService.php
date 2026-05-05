<?php

namespace App\Services;

use App\Models\MobileOtp;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class OtpService
{
    public function generate(User $user): string
    {
        $code = (string) random_int(100000, 999999);

        $user->mobileOtps()->delete();

        $user->mobileOtps()->create([
            'otp_code' => Hash::make($code),
            'expires_at' => now()->addMinutes(5),
            'attempts' => 0,
            'created_at' => now(),
        ]);

        return $code;
    }

    public function verify(User $user, string $inputOtp): bool
    {
        $otp = $this->latestForUser($user);

        if ($otp === null) {
            return false;
        }

        if ($otp->expires_at->isPast()) {
            $otp->delete();

            return false;
        }

        if ($otp->attempts >= MobileOtp::MAX_ATTEMPTS) {
            return false;
        }

        if (! Hash::check($inputOtp, $otp->otp_code)) {
            $otp->increment('attempts');

            return false;
        }

        $user->mobileOtps()->delete();

        return true;
    }

    public function secondsUntilResendAvailable(User $user): int
    {
        $latestOtp = $this->latestForUser($user);
        if ($latestOtp === null) {
            return 0;
        }

        $seconds = 60 - $latestOtp->created_at->diffInSeconds(now());

        return max($seconds, 0);
    }

    private function latestForUser(User $user): ?MobileOtp
    {
        return $user->mobileOtps()->first();
    }
}
