<?php

declare(strict_types=1);

namespace OguzhanBayirli\Turkiye\Identity;

/**
 * Turkish IBAN: "TR", two check digits, 5-digit bank code, a reserved digit
 * and a 16-digit account number. 26 characters in total.
 */
final class Iban
{
    public const LENGTH = 26;

    /**
     * Removes spaces and dashes and upper-cases the value. Does not validate.
     */
    public static function normalize(string $value): string
    {
        return strtoupper((string) preg_replace('/[\s\-]+/u', '', $value));
    }

    public static function isValid(mixed $value): bool
    {
        if (! is_string($value)) {
            return false;
        }

        $iban = self::normalize($value);

        if (preg_match('/^TR[0-9]{24}$/', $iban) !== 1) {
            return false;
        }

        return self::mod97(substr($iban, 4).self::lettersToDigits(substr($iban, 0, 4))) === 1;
    }

    /**
     * Groups of four, the way IBANs are printed on invoices and bank letters.
     */
    public static function format(string $value): string
    {
        return trim(chunk_split(self::normalize($value), 4, ' '));
    }

    /**
     * The 5-digit bank code (EFT code) inside the IBAN, e.g. "00010" for Ziraat.
     */
    public static function bankCode(string $value): ?string
    {
        if (! self::isValid($value)) {
            return null;
        }

        return substr(self::normalize($value), 4, 5);
    }

    /**
     * Builds a valid IBAN from a bank code and an account number. Meant for
     * factories and tests; the account number is padded to 16 digits.
     */
    public static function generate(string $bankCode = '00010', ?string $account = null): string
    {
        if (preg_match('/^[0-9]{5}$/', $bankCode) !== 1) {
            throw new \InvalidArgumentException('Bank code must be exactly 5 digits.');
        }

        if ($account === null) {
            $account = '';
            for ($i = 0; $i < 16; $i++) {
                $account .= (string) random_int(0, 9);
            }
        }

        if (preg_match('/^[0-9]{1,16}$/', $account) !== 1) {
            throw new \InvalidArgumentException('Account number must be 1 to 16 digits.');
        }

        $bban = $bankCode.'0'.str_pad($account, 16, '0', STR_PAD_LEFT);
        $check = 98 - self::mod97($bban.self::lettersToDigits('TR00'));

        return 'TR'.str_pad((string) $check, 2, '0', STR_PAD_LEFT).$bban;
    }

    private static function lettersToDigits(string $value): string
    {
        return (string) preg_replace_callback(
            '/[A-Z]/',
            static fn (array $m): string => (string) (ord($m[0]) - 55),
            $value,
        );
    }

    /**
     * Piecewise mod 97 so the 30-digit number never has to fit in an int.
     */
    private static function mod97(string $digits): int
    {
        $remainder = 0;

        foreach (str_split($digits, 7) as $chunk) {
            $remainder = (int) ($remainder.$chunk) % 97;
        }

        return $remainder;
    }
}
