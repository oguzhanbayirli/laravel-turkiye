<?php

declare(strict_types=1);

namespace OguzhanBayirli\Turkiye\Tcmb;

use Carbon\CarbonImmutable;
use SimpleXMLElement;

/**
 * A single daily bulletin ("Gösterge Niteliğindeki Merkez Bankası Kurları").
 */
final class RateSheet
{
    /**
     * @param  array<string, Rate>  $rates  keyed by ISO currency code
     */
    public function __construct(
        public readonly CarbonImmutable $date,
        public readonly ?string $bulletinNo,
        private readonly array $rates,
    ) {}

    public static function fromXml(string $xml, string $source = 'response'): self
    {
        if (trim($xml) === '') {
            throw TcmbException::invalidResponse($source, 'empty body');
        }

        $previous = libxml_use_internal_errors(true);

        try {
            $root = simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NONET);
            $error = libxml_get_last_error();
            libxml_clear_errors();
        } finally {
            libxml_use_internal_errors($previous);
        }

        if ($root === false) {
            throw TcmbException::invalidResponse($source, $error !== false ? trim($error->message) : 'not XML');
        }

        if ($root->getName() !== 'Tarih_Date') {
            throw TcmbException::invalidResponse($source, "unexpected root element <{$root->getName()}>");
        }

        try {
            $date = CarbonImmutable::createFromFormat('!d.m.Y', (string) $root['Tarih'], 'Europe/Istanbul');
        } catch (\Throwable) {
            $date = null;
        }

        if (! $date instanceof CarbonImmutable) {
            throw TcmbException::invalidResponse($source, 'missing or malformed Tarih attribute');
        }

        $rates = [];

        foreach ($root->Currency as $node) {
            $code = strtoupper(trim((string) ($node['CurrencyCode'] ?? $node['Kod'])));

            if ($code === '') {
                continue;
            }

            $rates[$code] = new Rate(
                code: $code,
                name: trim((string) $node->CurrencyName),
                turkishName: trim((string) $node->Isim),
                unit: max(1, (int) $node->Unit),
                forexBuying: self::number($node->ForexBuying),
                forexSelling: self::number($node->ForexSelling),
                banknoteBuying: self::number($node->BanknoteBuying),
                banknoteSelling: self::number($node->BanknoteSelling),
                crossRateUsd: self::number($node->CrossRateUSD),
            );
        }

        if ($rates === []) {
            throw TcmbException::invalidResponse($source, 'no <Currency> entries');
        }

        $bulletinNo = trim((string) $root['Bulten_No']);

        return new self($date, $bulletinNo === '' ? null : $bulletinNo, $rates);
    }

    public function has(string $code): bool
    {
        return isset($this->rates[strtoupper($code)]);
    }

    /**
     * @throws TcmbException when the currency is not in this bulletin
     */
    public function get(string $code): Rate
    {
        return $this->rates[strtoupper($code)]
            ?? throw TcmbException::unknownCurrency(strtoupper($code), $this->date->format('d.m.Y'));
    }

    /**
     * @return array<string, Rate>
     */
    public function all(): array
    {
        return $this->rates;
    }

    /**
     * @return list<string>
     */
    public function codes(): array
    {
        return array_keys($this->rates);
    }

    private static function number(?SimpleXMLElement $node): ?float
    {
        $value = trim((string) $node);

        return is_numeric($value) ? (float) $value : null;
    }
}
