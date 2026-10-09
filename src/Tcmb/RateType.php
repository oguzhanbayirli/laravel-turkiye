<?php

declare(strict_types=1);

namespace OguzhanBayirli\Turkiye\Tcmb;

/**
 * The four prices in a TCMB bulletin. Forex rates are what banks use for
 * transfers and what accounting usually needs; banknote rates are for cash.
 */
enum RateType: string
{
    case ForexBuying = 'ForexBuying';
    case ForexSelling = 'ForexSelling';
    case BanknoteBuying = 'BanknoteBuying';
    case BanknoteSelling = 'BanknoteSelling';
}
