<?php

namespace App\Support;

final class SignatureFeatures
{
    public static function hsmEnabled(): bool
    {
        return (bool) config('signature.features.hsm.enabled', false);
    }

    public static function awsCloudHsmEnabled(): bool
    {
        return (bool) config('signature.features.aws_cloudhsm.enabled', false);
    }

    public static function awsKmsEnabled(): bool
    {
        return (bool) config('signature.features.aws_kms.enabled', false);
    }

    public static function pkcs11Enabled(): bool
    {
        return (bool) config('signature.features.pkcs11.enabled', false);
    }

    public static function hsmRoutesEnabled(): bool
    {
        return self::hsmEnabled() && (bool) config('signature.routes.hsm', false);
    }

    public static function ocspRoutesEnabled(): bool
    {
        return (bool) config('signature.routes.ocsp', false);
    }

    public static function crlRoutesEnabled(): bool
    {
        return (bool) config('signature.routes.crl', false);
    }

    public static function scepRoutesEnabled(): bool
    {
        return (bool) config('signature.routes.scep', false);
    }

    public static function cmpRoutesEnabled(): bool
    {
        return (bool) config('signature.routes.cmp', false);
    }
}
