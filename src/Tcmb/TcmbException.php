<?php

declare(strict_types=1);

namespace OguzhanBayirli\Turkiye\Tcmb;

use RuntimeException;
use Throwable;

class TcmbException extends RuntimeException
{
    public static function requestFailed(string $url, string $reason, ?Throwable $previous = null): self
    {
        return new self("TCMB request to [{$url}] failed: {$reason}", 0, $previous);
    }

    public static function invalidResponse(string $url, string $reason): self
    {
        return new self("TCMB returned an unreadable bulletin from [{$url}]: {$reason}");
    }

    public static function noBulletin(string $date, int $lookbackDays): self
    {
        return new self("No TCMB bulletin found for {$date} or the {$lookbackDays} days before it.");
    }

    public static function unknownCurrency(string $code, string $date): self
    {
        return new self("Currency [{$code}] is not in the TCMB bulletin of {$date}.");
    }

    public static function missingPrice(string $code, RateType $type): self
    {
        return new self("The bulletin has no {$type->value} price for [{$code}].");
    }
}
