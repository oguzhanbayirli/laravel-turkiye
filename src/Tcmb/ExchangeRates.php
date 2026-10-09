<?php

declare(strict_types=1);

namespace OguzhanBayirli\Turkiye\Tcmb;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as Http;
use InvalidArgumentException;

/**
 * Reads the indicative exchange rates the Central Bank of the Republic of
 * Türkiye publishes every working day at around 15:30 Istanbul time.
 *
 *     $rates->today()->get('USD')->forexSelling;
 *     $rates->on('2026-10-04')->get('EUR');   // a Sunday: returns Friday's bulletin
 *     $rates->rate('JPY')->perUnit();          // one yen, not a hundred
 */
class ExchangeRates
{
    private const MISSING = '__missing__';

    private const TIMEZONE = 'Europe/Istanbul';

    /**
     * @param  array{base_url?: string, timeout?: int, connect_timeout?: int, retries?: int, max_lookback_days?: int, cache?: array{store?: string|null, prefix?: string, today_ttl?: int, archive_ttl?: int|null}}  $config
     */
    public function __construct(
        private readonly Http $http,
        private readonly Cache $cache,
        private readonly array $config = [],
    ) {}

    /**
     * The latest bulletin (today.xml). Before 15:30, or on a day off, this is
     * the previous working day's bulletin; check $sheet->date.
     */
    public function today(): RateSheet
    {
        $url = $this->baseUrl().'/today.xml';
        $key = $this->cacheKey('today');

        $xml = $this->cache->get($key);

        if (! is_string($xml)) {
            $xml = $this->fetch($url) ?? throw TcmbException::requestFailed($url, 'HTTP 404');
            $sheet = RateSheet::fromXml($xml, $url);
            $this->cache->put($key, $xml, (int) ($this->config['cache']['today_ttl'] ?? 600));

            return $sheet;
        }

        return RateSheet::fromXml($xml, $url);
    }

    /**
     * The bulletin published on $date, or on the closest working day before
     * it when TCMB published nothing that day (weekends, public holidays).
     *
     * @throws InvalidArgumentException for dates in the future
     * @throws TcmbException when nothing is found within max_lookback_days
     */
    public function on(DateTimeInterface|string $date): RateSheet
    {
        $requested = $this->normalizeDate($date);
        $today = CarbonImmutable::now(self::TIMEZONE)->startOfDay();

        if ($requested->greaterThan($today)) {
            throw new InvalidArgumentException("No exchange rates exist yet for {$requested->format('Y-m-d')}.");
        }

        $lookback = max(0, (int) ($this->config['max_lookback_days'] ?? 10));

        for ($day = 0; $day <= $lookback; $day++) {
            $candidate = $requested->subDays($day);
            $sheet = $this->archive($candidate, $candidate->lessThan($today));

            if ($sheet !== null) {
                return $sheet;
            }
        }

        throw TcmbException::noBulletin($requested->format('Y-m-d'), $lookback);
    }

    /**
     * One currency, from today's bulletin or from the one in effect on $date.
     */
    public function rate(string $code, DateTimeInterface|string|null $date = null): Rate
    {
        $sheet = $date === null ? $this->today() : $this->on($date);

        return $sheet->get($code);
    }

    private function archive(CarbonImmutable $date, bool $isPast): ?RateSheet
    {
        $url = sprintf('%s/%s/%s.xml', $this->baseUrl(), $date->format('Ym'), $date->format('dmY'));
        $key = $this->cacheKey($date->format('Ymd'));

        $cached = $this->cache->get($key);

        if ($cached === self::MISSING) {
            return null;
        }

        if (is_string($cached)) {
            return RateSheet::fromXml($cached, $url);
        }

        $xml = $this->fetch($url);

        if ($xml === null) {
            // A past day without a bulletin stays that way. Today's may still
            // appear after 15:30, so that miss is not remembered.
            if ($isPast) {
                $this->remember($key, self::MISSING);
            }

            return null;
        }

        $sheet = RateSheet::fromXml($xml, $url);
        $this->remember($key, $xml);

        return $sheet;
    }

    /**
     * @return string|null the body, or null when TCMB answered 404
     */
    private function fetch(string $url): ?string
    {
        try {
            $response = $this->http
                ->timeout((int) ($this->config['timeout'] ?? 10))
                ->connectTimeout((int) ($this->config['connect_timeout'] ?? 5))
                ->retry(
                    max(1, 1 + (int) ($this->config['retries'] ?? 1)),
                    200,
                    static fn (\Throwable $e): bool => $e instanceof ConnectionException,
                    throw: false,
                )
                ->withHeaders(['Accept' => 'application/xml, text/xml'])
                ->get($url);
        } catch (ConnectionException $e) {
            throw TcmbException::requestFailed($url, $e->getMessage(), $e);
        }

        if ($response->status() === 404) {
            return null;
        }

        if (! $response->successful()) {
            throw TcmbException::requestFailed($url, 'HTTP '.$response->status());
        }

        return $response->body();
    }

    private function remember(string $key, string $value): void
    {
        $ttl = array_key_exists('archive_ttl', $this->config['cache'] ?? [])
            ? $this->config['cache']['archive_ttl']
            : 60 * 60 * 24 * 30;

        $ttl === null
            ? $this->cache->forever($key, $value)
            : $this->cache->put($key, $value, (int) $ttl);
    }

    private function normalizeDate(DateTimeInterface|string $date): CarbonImmutable
    {
        if ($date instanceof DateTimeInterface) {
            // Keep the calendar date the caller meant; only the zone changes.
            return CarbonImmutable::create(
                (int) $date->format('Y'),
                (int) $date->format('m'),
                (int) $date->format('d'),
                0, 0, 0,
                self::TIMEZONE,
            ) ?? throw new InvalidArgumentException('Invalid date.');
        }

        try {
            return CarbonImmutable::parse($date, self::TIMEZONE)->startOfDay();
        } catch (\Throwable $e) {
            throw new InvalidArgumentException("Cannot read [{$date}] as a date.", 0, $e);
        }
    }

    private function baseUrl(): string
    {
        return rtrim($this->config['base_url'] ?? 'https://www.tcmb.gov.tr/kurlar', '/');
    }

    private function cacheKey(string $suffix): string
    {
        return ($this->config['cache']['prefix'] ?? 'turkiye.tcmb').'.'.$suffix;
    }
}
