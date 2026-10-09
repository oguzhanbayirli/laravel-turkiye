<?php

declare(strict_types=1);

namespace OguzhanBayirli\Turkiye\Words;

use InvalidArgumentException;

/**
 * Writes a lira amount out in words.
 *
 *     (new AmountInWords)->convert('1234.56');
 *     // Bin İki Yüz Otuz Dört Türk Lirası Elli Altı Kuruş
 *
 *     (new AmountInWords)->invoice('1234.56');
 *     // Yalnız #BinİkiYüzOtuzDörtTL,ElliAltıKr#
 *
 * Pass amounts as strings ("1234.56", which is also what Laravel's decimal
 * cast returns) when the exact kuruş matters. Floats are accepted and rounded
 * to two decimals, with the usual floating point caveats.
 */
final class AmountInWords
{
    public function __construct(private readonly WordsFormat $defaultFormat = new WordsFormat) {}

    public function convert(int|float|string $amount, ?WordsFormat $format = null): string
    {
        $format ??= $this->defaultFormat;
        [$negative, $lira, $kurus] = self::split($amount);

        $parts = [];

        if ($lira !== '0' || $kurus === 0) {
            $parts[] = $this->part(NumberToWords::words($lira), $format->liraLabel, $format);
        }

        if ($kurus > 0 || $format->showZeroKurus) {
            $parts[] = $this->part(NumberToWords::words($kurus), $format->kurusLabel, $format);
        }

        $text = implode($format->partSeparator, $parts);

        if ($negative) {
            $text = $format->casing->apply('eksi').$format->wordSeparator.$text;
        }

        return $format->prefix.$text.$format->suffix;
    }

    /**
     * Shortcut for convert() with the WordsFormat::invoice() preset.
     */
    public function invoice(int|float|string $amount): string
    {
        return $this->convert($amount, WordsFormat::invoice());
    }

    /**
     * Splits an amount into sign, whole lira (as a digit string) and kuruş,
     * rounding half away from zero at the second decimal.
     *
     * @return array{bool, numeric-string, int}
     */
    public static function split(int|float|string $amount): array
    {
        if (is_float($amount)) {
            if (! is_finite($amount)) {
                throw new InvalidArgumentException('Amount must be a finite number.');
            }
            $amount = number_format($amount, 2, '.', '');
        }

        $amount = trim((string) $amount);

        if (preg_match('/^(-?)([0-9]+)(?:\.([0-9]+))?$/', $amount, $m) !== 1) {
            throw new InvalidArgumentException(
                "Amount [{$amount}] must look like 1234 or 1234.56 (dot as the decimal separator, no thousands separator)."
            );
        }

        $lira = ltrim($m[2], '0');
        $lira = $lira === '' ? '0' : $lira;
        $decimals = str_pad($m[3] ?? '', 3, '0');
        $kurus = (int) substr($decimals, 0, 2);

        if ((int) $decimals[2] >= 5) {
            $kurus++;
        }

        if ($kurus === 100) {
            $kurus = 0;
            $lira = self::increment($lira);
        }

        $negative = $m[1] === '-' && ($lira !== '0' || $kurus !== 0);

        /** @var numeric-string $lira */
        return [$negative, $lira, $kurus];
    }

    /**
     * @param  list<string>  $words
     */
    private function part(array $words, string $label, WordsFormat $format): string
    {
        $words = array_map($format->casing->apply(...), $words);

        // Labels are written as given in title case ("TL", "Türk Lirası")
        // and only forced to upper or lower when the whole text is.
        if ($format->casing !== Casing::Title) {
            $label = implode(' ', array_map($format->casing->apply(...), explode(' ', $label)));
        }

        return implode($format->wordSeparator, $words).$format->labelSeparator.$label;
    }

    /**
     * Adds one to an arbitrarily long digit string ("999" becomes "1000").
     */
    private static function increment(string $digits): string
    {
        $i = strlen($digits) - 1;

        while ($i >= 0 && $digits[$i] === '9') {
            $digits[$i] = '0';
            $i--;
        }

        return $i < 0 ? '1'.$digits : substr_replace($digits, (string) ((int) $digits[$i] + 1), $i, 1);
    }
}
