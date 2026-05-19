<?php

namespace App\Services\Ekyc;

use App\Models\User;

class EkycNameMatcher
{
    public function match(User $user, string $ocrText): EkycNameMatchResult
    {
        $firstName = $user->resolvedFirstName();
        $lastName = $user->resolvedLastName();
        $middleName = $user->resolvedMiddleName();

        if ($firstName === '' || $lastName === '') {
            return new EkycNameMatchResult(
                matched: false,
                message: __('Your account name is incomplete. Please contact support.'),
                ocrText: $ocrText,
            );
        }

        $haystack = $this->normalize($ocrText);

        if (! $this->namePartMatches($firstName, $haystack)) {
            return new EkycNameMatchResult(
                matched: false,
                message: __('The first name on your ID does not match your account (":name").', [
                    'name' => $user->displayFirstName(),
                ]),
                ocrText: $ocrText,
            );
        }

        if (! $this->namePartMatches($lastName, $haystack)) {
            return new EkycNameMatchResult(
                matched: false,
                message: __('The last name on your ID does not match your account (":name").', [
                    'name' => $user->displayLastName(),
                ]),
                ocrText: $ocrText,
            );
        }

        if ($middleName !== '' && ! $this->middleNameMatches($middleName, $haystack)) {
            return new EkycNameMatchResult(
                matched: false,
                message: __('The middle name on your ID does not match your account.'),
                ocrText: $ocrText,
            );
        }

        return new EkycNameMatchResult(
            matched: true,
            message: __('Identity document name matches your account.'),
            ocrText: $ocrText,
        );
    }

    private function middleNameMatches(string $middleName, string $haystack): bool
    {
        if ($this->namePartMatches($middleName, $haystack)) {
            return true;
        }

        $initial = mb_substr($this->normalize($middleName), 0, 1);

        if ($initial === '') {
            return true;
        }

        $pattern = '/\b'.preg_quote($initial, '/').'\.?\b/u';

        return (bool) preg_match($pattern, $haystack);
    }

    private function namePartMatches(string $needle, string $haystack): bool
    {
        $normalizedNeedle = $this->normalize($needle);

        if ($normalizedNeedle === '') {
            return false;
        }

        if (str_contains($haystack, $normalizedNeedle)) {
            return true;
        }

        $threshold = (int) config('ekyc.name_match_threshold', 85);
        $tokens = preg_split('/\s+/', $haystack) ?: [];

        foreach ($tokens as $token) {
            similar_text($normalizedNeedle, $token, $percent);

            if ($percent >= $threshold) {
                return true;
            }
        }

        similar_text($normalizedNeedle, $haystack, $percent);

        return $percent >= $threshold;
    }

    private function normalize(string $value): string
    {
        $value = mb_strtoupper(trim($value));
        $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
        $value = preg_replace('/[^A-Z0-9\s]/', ' ', $value) ?? $value;

        return preg_replace('/\s+/', ' ', trim($value)) ?? '';
    }
}
