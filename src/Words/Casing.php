<?php

declare(strict_types=1);

namespace OguzhanBayirli\Turkiye\Words;

enum Casing: string
{
    case Title = 'title';
    case Upper = 'upper';
    case Lower = 'lower';

    /**
     * Turkish-aware: "i" becomes "İ" and "ı" becomes "I", which
     * mb_strtoupper() alone gets wrong.
     */
    public function apply(string $word): string
    {
        return match ($this) {
            self::Lower => self::lower($word),
            self::Upper => self::upper($word),
            self::Title => self::upper(mb_substr($word, 0, 1)).self::lower(mb_substr($word, 1)),
        };
    }

    private static function upper(string $value): string
    {
        return mb_strtoupper(strtr($value, ['i' => 'İ', 'ı' => 'I']), 'UTF-8');
    }

    private static function lower(string $value): string
    {
        return mb_strtolower(strtr($value, ['İ' => 'i', 'I' => 'ı']), 'UTF-8');
    }
}
