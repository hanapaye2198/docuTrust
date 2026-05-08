<?php

namespace App\Support;

final class AuthSession
{
    public const TWO_FACTOR_PASSED = '2fa_passed';

    public const PENDING_TWO_FACTOR_STARTED_AT = 'auth.two_factor.started_at';

    public const REGISTER_PENDING_DATA = 'auth.register.pending_data';

    public const PENDING_TWO_FACTOR_USER_ID = 'auth.two_factor.pending_user_id';

    public const PENDING_TWO_FACTOR_REMEMBER = 'auth.two_factor.remember';

    public const SETUP_SECRET = 'auth.two_factor.setup_secret';

    public const REGISTER_TWO_FACTOR_SECRET = 'auth.register_two_factor.pending_secret';

    public const REGISTER_TWO_FACTOR_USER_ID = 'auth.register_two_factor.pending_user_id';

    public const TRUSTED_DEVICE_UNTIL = 'auth.two_factor.trusted_device_until';
}
