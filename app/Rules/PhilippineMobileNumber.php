<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class PhilippineMobileNumber implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $digits = preg_replace('/\D+/', '', (string) $value) ?? '';

        $isLocal = (bool) preg_match('/^09\d{9}$/', $digits);
        $isInternational = (bool) preg_match('/^639\d{9}$/', $digits);

        if (! $isLocal && ! $isInternational) {
            $fail(__('Enter a valid Philippine mobile number (e.g. 09171234567 or +639171234567).'));
        }
    }
}
