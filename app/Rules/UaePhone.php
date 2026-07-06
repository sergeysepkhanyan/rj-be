<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Phone validation with strict UAE length. UAE numbers (+971 / 00971 / local 0…)
 * must carry exactly 9 national digits (e.g. +971501234567). Non-UAE
 * international numbers are bounded to the E.164 range (8–15 digits).
 */
class UaePhone implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return; // presence is handled by required/nullable rules
        }

        $digits = preg_replace('/[^0-9]/', '', $raw);

        if (str_starts_with($raw, '+971') || str_starts_with($digits, '00971')) {
            $national = str_starts_with($digits, '00971')
                ? substr($digits, 5)
                : substr($digits, 3);
            if (strlen($national) !== 9) {
                $fail('The :attribute must be a valid UAE phone number (+971 followed by 9 digits).');
            }

            return;
        }

        // Local UAE format without country code: 0 + 9 digits.
        if (! str_starts_with($raw, '+') && str_starts_with($digits, '0')) {
            if (strlen($digits) !== 10) {
                $fail('The :attribute must be a valid UAE phone number (0 followed by 9 digits).');
            }

            return;
        }

        // Other international numbers: E.164 bounds.
        if (strlen($digits) < 8 || strlen($digits) > 15) {
            $fail('The :attribute must be a valid phone number (8–15 digits).');
        }
    }
}
