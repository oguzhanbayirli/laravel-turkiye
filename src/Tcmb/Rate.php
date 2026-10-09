<?php

declare(strict_types=1);

namespace OguzhanBayirli\Turkiye\Tcmb;

/**
 * One currency line of a bulletin.
 *
 * Prices are for $unit units of the currency. Almost everything is quoted
 * per 1, but JPY is quoted per 100, so 100 yen cost $forexSelling lira.
 * Use perUnit() or toLira() if you do not want to think about that.
 *
 * Any price can be null: SDR (XDR) has no banknote rates, for example.
 */
final class Rate
{
    public function __construct(
        public readonly string $code,
        public readonly string $name,
        public readonly string $turkishName,
        public readonly int $unit,
        public readonly ?float $forexBuying,
        public readonly ?float $forexSelling,
        public readonly ?float $banknoteBuying,
        public readonly ?float $banknoteSelling,
        public readonly ?float $crossRateUsd = null,
    ) {}

    public function get(RateType $type): ?float
    {
        return match ($type) {
            RateType::ForexBuying => $this->forexBuying,
            RateType::ForexSelling => $this->forexSelling,
            RateType::BanknoteBuying => $this->banknoteBuying,
            RateType::BanknoteSelling => $this->banknoteSelling,
        };
    }

    /**
     * Price of a single unit of the currency, e.g. one yen.
     */
    public function perUnit(RateType $type = RateType::ForexSelling): ?float
    {
        $value = $this->get($type);

        return $value === null ? null : $value / $this->unit;
    }

    /**
     * Converts an amount in this currency to lira. Not rounded.
     *
     * @throws TcmbException when the bulletin has no price of that type
     */
    public function toLira(int|float $amount, RateType $type = RateType::ForexSelling): float
    {
        $perUnit = $this->perUnit($type);

        if ($perUnit === null) {
            throw TcmbException::missingPrice($this->code, $type);
        }

        return $amount * $perUnit;
    }
}
