<?php

namespace App\Services;

use App\Models\User;
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
        if (! $user->two_factor_enabled || $user->two_factor_secret === null || $user->two_factor_secret === '') {
            return false;
        }

        $secret = $user->two_factor_secret;

        return $this->google2fa->verifyKey($secret, $code);
    }

    public function verifyRawSecret(string $secret, string $code): bool
    {
        return $this->google2fa->verifyKey($secret, $code);
    }
}
