<?php

declare(strict_types=1);

namespace OguzhanBayirli\Turkiye\Identity;

/**
 * T.C. Kimlik No (Turkish national identity number).
 *
 * Checks the structure and the two check digits only. A number that passes
 * is well-formed; it is not proof that a person with this number exists.
 */
final class Tckn
{
    public static function isValid(mixed $value): bool
    {
        if (! is_string($value) && ! is_int($value)) {
            return false;
        }

        $value = trim((string) $value);

        if (preg_match('/^[1-9][0-9]{10}$/', $value) !== 1) {
            return false;
        }

        $digits = array_map('intval', str_split($value));

        return self::checkDigits(array_slice($digits, 0, 9)) === [$digits[9], $digits[10]];
    }

    /**
     * Builds a structurally valid number. Meant for factories and tests.
     */
    public static function generate(): string
    {
        $digits = [random_int(1, 9)];

        for ($i = 1; $i < 9; $i++) {
            $digits[] = random_int(0, 9);
        }

        return implode('', [...$digits, ...self::checkDigits($digits)]);
    }

    /**
     * @param  list<int>  $first9
     * @return array{int, int}
     */
    private static function checkDigits(array $first9): array
    {
        $odd = $first9[0] + $first9[2] + $first9[4] + $first9[6] + $first9[8];
        $even = $first9[1] + $first9[3] + $first9[5] + $first9[7];

        // ((odd * 7) - even) can be negative, so normalise into 0..9.
        $tenth = ((($odd * 7) - $even) % 10 + 10) % 10;
        $eleventh = (array_sum($first9) + $tenth) % 10;

        return [$tenth, $eleventh];
    }
}
