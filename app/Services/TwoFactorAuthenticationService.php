<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

final class TwoFactorAuthenticationService
{
    public function __construct(
        private readonly Google2FA $google2fa,
    ) {}

    public function generateSecretKey(): string
    {
        return $this->google2fa->generateSecretKey();
    }

    /**
     * otpauth URI for registration onboarding (issuer DocuTrust, Google Authenticator compatible).
     */
    public function registrationOtpAuthUri(string $email, string $secret): string
    {
        $issuer = 'DocuTrust';
        $label = $issuer.':'.$email;

        return 'otpauth://totp/'.rawurlencode($label).'?secret='.rawurlencode($secret).'&issuer='.rawurlencode($issuer);
    }

    /**
     * @return array{uri: string, inline: string}
     */
    public function registrationQrCodeData(string $email, string $secret): array
    {
        $uri = $this->registrationOtpAuthUri($email, $secret);

        return [
            'uri' => $uri,
            'inline' => 'https://chart.googleapis.com/chart?chs=280x280&chld=M|0&cht=qr&chl='.rawurlencode($uri),
        ];
    }

    /**
     * @return array{url: string, inline: string}
     */
    public function qrCodeData(User $user, string $secret): array
    {
        $holder = $user->email;
        $issuer = config('app.name', 'DocuTrust');

        $url = $this->google2fa->getQRCodeUrl($issuer, $holder, $secret);

        return [
            'url' => $url,
            'inline' => 'https://chart.googleapis.com/chart?chs=200x200&chld=M|0&cht=qr&chl='.rawurlencode($url),
        ];
    }

    public function verify(User $user, string $code): bool
    {
        if (
            ! $user->two_factor_enabled
            || $user->two_factor_confirmed_at === null
            || $user->two_factor_secret === null
            || $user->two_factor_secret === ''
        ) {
            return false;
        }

        $secret = $user->two_factor_secret;

        return $this->google2fa->verifyKey($secret, $code);
    }

    public function verifyRawSecret(string $secret, string $code): bool
    {
        return $this->google2fa->verifyKey($secret, $code);
    }

    /**
     * @return list<string>
     */
    public function generateRecoveryCodes(int $count = 8): array
    {
        $codes = [];

        for ($index = 0; $index < $count; $index++) {
            $codes[] = Str::lower(Str::random(4)).'-'.Str::lower(Str::random(4));
        }

        return $codes;
    }

    /**
     * @return list<string>
     */
    public function enableForUser(User $user, string $secret): array
    {
        $recoveryCodes = $this->generateRecoveryCodes();

        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => $recoveryCodes,
            'two_factor_enabled' => true,
            'two_factor_confirmed_at' => now(),
        ])->save();

        return $recoveryCodes;
    }

    public function disableForUser(User $user): void
    {
        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_enabled' => false,
            'two_factor_confirmed_at' => null,
            'mfa_enabled' => false,
        ])->save();
    }

    public function regenerateRecoveryCodes(User $user): array
    {
        $codes = $this->generateRecoveryCodes();

        $user->forceFill([
            'two_factor_recovery_codes' => $codes,
        ])->save();

        return $codes;
    }

    public function consumeRecoveryCode(User $user, string $candidate): bool
    {
        $codes = collect($user->two_factor_recovery_codes ?? [])
            ->filter(fn ($code) => is_string($code) && $code !== '')
            ->map(fn (string $code) => Str::lower(trim($code)))
            ->values();

        $normalizedCandidate = Str::lower(trim($candidate));
        if (! $codes->contains($normalizedCandidate)) {
            return false;
        }

        if ($codes->count() <= 1) {
            return false;
        }

        $remaining = $codes->reject(fn (string $code) => $code === $normalizedCandidate)->values()->all();
        $user->forceFill([
            'two_factor_recovery_codes' => $remaining,
        ])->save();

        return true;
    }

    public function hasAtLeastOneRecoveryCode(User $user): bool
    {
        return collect($user->two_factor_recovery_codes ?? [])
            ->filter(fn ($code) => is_string($code) && trim($code) !== '')
            ->isNotEmpty();
    }
}
