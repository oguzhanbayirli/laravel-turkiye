<?php

declare(strict_types=1);

namespace OguzhanBayirli\Turkiye\Words;

use InvalidArgumentException;

/**
 * Spells whole numbers in Turkish, written the way TDK recommends: each
 * word separate and lower case ("bin iki yüz otuz dört").
 *
 * "bir" is dropped before "yüz" and before the thousands group, so 1100 is
 * "bin yüz", not "bir bin bir yüz". It stays before larger scales:
 * 1 000 000 is "bir milyon".
 */
final class NumberToWords
{
    private const ONES = ['', 'bir', 'iki', 'üç', 'dört', 'beş', 'altı', 'yedi', 'sekiz', 'dokuz'];

    private const TENS = ['', 'on', 'yirmi', 'otuz', 'kırk', 'elli', 'altmış', 'yetmiş', 'seksen', 'doksan'];

    private const SCALES = [
        '', 'bin', 'milyon', 'milyar', 'trilyon', 'katrilyon', 'kentilyon',
        'seksilyon', 'septilyon', 'oktilyon', 'nonilyon', 'desilyon',
    ];

    /**
     * @param  int|numeric-string  $number  A whole number. Strings may be longer than PHP_INT_MAX.
     */
    public static function spell(int|string $number): string
    {
        return implode(' ', self::words($number));
    }

    /**
     * Same as spell() but returns the individual words, which makes it easy to
     * join them differently (invoices often write them without spaces).
     *
     * @param  int|numeric-string  $number
     * @return list<string>
     */
    public static function words(int|string $number): array
    {
        $digits = trim((string) $number);

        if (preg_match('/^(-?)([0-9]+)$/', $digits, $m) !== 1) {
            throw new InvalidArgumentException("Not a whole number: [{$digits}].");
        }

        $negative = $m[1] === '-';
        $digits = ltrim($m[2], '0');

        if ($digits === '') {
            return ['sıfır'];
        }

        $groups = str_split(str_pad($digits, (int) ceil(strlen($digits) / 3) * 3, '0', STR_PAD_LEFT), 3);

        if (count($groups) > count(self::SCALES)) {
            throw new InvalidArgumentException('Numbers above the desilyon range (10^36) are not supported.');
        }

        $words = $negative ? ['eksi'] : [];
        $scale = count($groups) - 1;

        foreach ($groups as $group) {
            $value = (int) $group;

            if ($value !== 0) {
                // "bin", never "bir bin".
                if (! ($scale === 1 && $value === 1)) {
                    array_push($words, ...self::hundreds($value));
                }

                if ($scale > 0) {
                    $words[] = self::SCALES[$scale];
                }
            }

            $scale--;
        }

        return $words;
    }

    /**
     * @return list<string>
     */
    private static function hundreds(int $value): array
    {
        $words = [];
        $hundreds = intdiv($value, 100);
        $tens = intdiv($value % 100, 10);
        $ones = $value % 10;

        if ($hundreds > 0) {
            if ($hundreds > 1) {
                $words[] = self::ONES[$hundreds];
            }
            $words[] = 'yüz';
        }

        if ($tens > 0) {
            $words[] = self::TENS[$tens];
        }

        if ($ones > 0) {
            $words[] = self::ONES[$ones];
        }

        return $words;
    }
}
