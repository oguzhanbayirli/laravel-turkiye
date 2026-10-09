<?php

declare(strict_types=1);

namespace OguzhanBayirli\Turkiye\Facades;

use Illuminate\Support\Facades\Facade;
use OguzhanBayirli\Turkiye\Words\WordsFormat;

/**
 * @method static string convert(int|float|string $amount, ?WordsFormat $format = null)
 * @method static string invoice(int|float|string $amount)
 *
 * @see \OguzhanBayirli\Turkiye\Words\AmountInWords
 */
class AmountInWords extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \OguzhanBayirli\Turkiye\Words\AmountInWords::class;
    }
}
