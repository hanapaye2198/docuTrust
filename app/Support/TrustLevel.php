<?php

namespace App\Support;

final class TrustLevel
{
    public const LEVEL_BASIC_ELECTRONIC = 1;

    public const LEVEL_VERIFIED_ELECTRONIC = 2;

    public const LEVEL_ADVANCED_DIGITAL = 3;

    public const LEVEL_PKI_BACKED = 4;

    public const LEVEL_GOVERNMENT_GRADE = 5;

    /**
     * @return array{
     *   level: int,
     *   label: string,
     *   description: string,
     *   capped: bool,
     *   cap_reason: string|null
     * }
     */
    public static function evaluate(): array
    {
        $signingBackend = (string) config('docutrust.pki.signing_backend', 'app_managed');
        $hasBlockchain = (string) config('services.blockchain.base_url', '') !== '';
        $remoteTimestamp = (bool) config('services.remote_signing.csc.timestamp_enabled', false);
        $hsmEnabled = SignatureFeatures::hsmEnabled();
        $padesEnabled = (bool) config('signature.features.pades.enabled', false);

        $intrinsic = self::LEVEL_BASIC_ELECTRONIC;

        if ($signingBackend === 'app_managed' || $signingBackend === 'remote_managed') {
            $intrinsic = self::LEVEL_ADVANCED_DIGITAL;
        }

        if ($signingBackend === 'remote_managed' && $remoteTimestamp) {
            $intrinsic = self::LEVEL_PKI_BACKED;
        }

        if ($hsmEnabled && $padesEnabled && $signingBackend === 'remote_managed') {
            $intrinsic = self::LEVEL_GOVERNMENT_GRADE;
        }

        $maxLevel = self::maxLevelForPhase($signingBackend, $hsmEnabled);
        $level = min($intrinsic, $maxLevel);
        $capped = $level < $intrinsic;

        return [
            'level' => $level,
            'label' => self::labelFor($level),
            'description' => self::descriptionFor($level),
            'capped' => $capped,
            'cap_reason' => $capped ? self::capReason($maxLevel, $signingBackend, $hsmEnabled) : null,
            'has_blockchain' => $hasBlockchain,
            'signing_backend' => $signingBackend,
        ];
    }

    public static function labelFor(int $level): string
    {
        return match ($level) {
            self::LEVEL_BASIC_ELECTRONIC => __('Basic Electronic Signature'),
            self::LEVEL_VERIFIED_ELECTRONIC => __('Verified Electronic Signature'),
            self::LEVEL_ADVANCED_DIGITAL => __('Advanced Digital Signature'),
            self::LEVEL_PKI_BACKED => __('PKI-backed Digital Signature'),
            self::LEVEL_GOVERNMENT_GRADE => __('Government-grade Trust Signature'),
            default => __('Unknown trust level'),
        };
    }

    public static function descriptionFor(int $level): string
    {
        return match ($level) {
            self::LEVEL_BASIC_ELECTRONIC => __('Visual capture only without cryptographic seal.'),
            self::LEVEL_VERIFIED_ELECTRONIC => __('Signer access controls and identity checks without full PKI seal.'),
            self::LEVEL_ADVANCED_DIGITAL => __('RSA-SHA256 document hash signing with internal or provider-managed certificates.'),
            self::LEVEL_PKI_BACKED => __('Remote CSC signing with timestamp and certificate chain evidence.'),
            self::LEVEL_GOVERNMENT_GRADE => __('Qualified profile with HSM, accredited TSA, and PAdES (when enabled).'),
            default => '',
        };
    }

    private static function maxLevelForPhase(string $signingBackend, bool $hsmEnabled): int
    {
        if ($hsmEnabled && $signingBackend === 'remote_managed') {
            return self::LEVEL_GOVERNMENT_GRADE;
        }

        if ($signingBackend === 'remote_managed') {
            return self::LEVEL_PKI_BACKED;
        }

        if ($signingBackend === 'app_managed' && ! $hsmEnabled) {
            return self::LEVEL_ADVANCED_DIGITAL;
        }

        return self::LEVEL_VERIFIED_ELECTRONIC;
    }

    private static function capReason(int $maxLevel, string $signingBackend, bool $hsmEnabled): string
    {
        if ($maxLevel === self::LEVEL_ADVANCED_DIGITAL && ! $hsmEnabled) {
            return __('Early production uses app-managed PKI without HSM; trust level capped at Level 3.');
        }

        if ($signingBackend === 'app_managed') {
            return __('Enable remote_managed CSC signing with timestamp for Level 4.');
        }

        return __('Additional trust services required for higher levels.');
    }
}
