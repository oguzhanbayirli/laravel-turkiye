<?php

declare(strict_types=1);

namespace OguzhanBayirli\Turkiye\Identity;

/**
 * Vergi Kimlik No (10-digit tax number issued by the Revenue Administration).
 *
 * The last digit is a check digit computed from the first nine. The same
 * algorithm is used by python-stdnum (stdnum.tr.vkn) and by the many
 * e-invoice integrations that validate VKN offline.
 */
final class Vkn
{
    public static function isValid(mixed $value): bool
    {
        if (! is_string($value) && ! is_int($value)) {
            return false;
        }

        $value = trim((string) $value);

        if (preg_match('/^[0-9]{10}$/', $value) !== 1) {
            return false;
        }

        return self::checkDigit(substr($value, 0, 9)) === (int) $value[9];
    }

    /**
     * Builds a structurally valid number. Meant for factories and tests.
     */
    public static function generate(): string
    {
        $first9 = '';

        for ($i = 0; $i < 9; $i++) {
            $first9 .= (string) random_int(0, 9);
        }

        return $first9.self::checkDigit($first9);
    }

    private static function checkDigit(string $first9): int
    {
        $sum = 0;

        for ($i = 0; $i < 9; $i++) {
            $step = ((int) $first9[$i] + 9 - $i) % 10;

            if ($step === 0) {
                continue;
            }

            $weighted = ($step * (2 ** (9 - $i))) % 9;
            $sum += $weighted === 0 ? 9 : $weighted;
        }

        return (10 - $sum % 10) % 10;
    }
}
