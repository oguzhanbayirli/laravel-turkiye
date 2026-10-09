<?php

declare(strict_types=1);

namespace OguzhanBayirli\Turkiye\Facades;

use DateTimeInterface;
use Illuminate\Support\Facades\Facade;
use OguzhanBayirli\Turkiye\Tcmb\ExchangeRates;
use OguzhanBayirli\Turkiye\Tcmb\Rate;
use OguzhanBayirli\Turkiye\Tcmb\RateSheet;

/**
 * @method static RateSheet today()
 * @method static RateSheet on(DateTimeInterface|string $date)
 * @method static Rate rate(string $code, DateTimeInterface|string|null $date = null)
 *
 * @see ExchangeRates
 */
class Tcmb extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ExchangeRates::class;
    }
}
