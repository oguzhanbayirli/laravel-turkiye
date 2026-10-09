<?php

use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use OguzhanBayirli\Turkiye\Facades\Tcmb;
use OguzhanBayirli\Turkiye\Tcmb\ExchangeRates;
use OguzhanBayirli\Turkiye\Tcmb\TcmbException;

// No test here talks to TCMB. Responses come from tests/Fixtures, which holds
// real bulletins downloaded once (08.10.2026 and 02.10.2026) and the HTML page
// TCMB returns with a 404 for days without a bulletin.

const BASE = 'https://www.tcmb.gov.tr/kurlar';

beforeEach(fn () => Http::preventStrayRequests());

afterEach(fn () => Carbon::setTestNow());

function notFound(): PromiseInterface
{
    return Http::response(load_fixture('not-found.html'), 404, ['Content-Type' => 'text/html']);
}

it('reads today.xml', function () {
    Http::fake([BASE.'/today.xml' => Http::response(load_fixture('today.xml'))]);

    $sheet = app(ExchangeRates::class)->today();

    expect($sheet->date->format('d.m.Y'))->toBe('08.10.2026')
        ->and($sheet->get('USD')->forexSelling)->toBe(49.2152);

    Http::assertSent(fn (Request $r) => $r->url() === BASE.'/today.xml' && $r->method() === 'GET');
});

it('caches today.xml for the configured time', function () {
    Carbon::setTestNow('2026-10-09 16:00:00');
    Http::fake([BASE.'/today.xml' => Http::response(load_fixture('today.xml'))]);

    $rates = app(ExchangeRates::class);
    $rates->today();
    $rates->today();
    Http::assertSentCount(1);

    Carbon::setTestNow('2026-10-09 16:11:00');
    $rates->today();
    Http::assertSentCount(2);
});

it('reads a dated bulletin from the archive', function () {
    Carbon::setTestNow('2026-10-09 12:00:00');
    Http::fake([BASE.'/202610/02102026.xml' => Http::response(load_fixture('02102026.xml'))]);

    $sheet = Tcmb::on('2026-10-02');

    expect($sheet->date->format('Y-m-d'))->toBe('2026-10-02')
        ->and($sheet->get('USD')->forexBuying)->toBe(48.9699);
});

it('falls back to Friday on a weekend', function () {
    Carbon::setTestNow('2026-10-09 12:00:00');
    Http::fake([
        BASE.'/202610/04102026.xml' => notFound(),
        BASE.'/202610/03102026.xml' => notFound(),
        BASE.'/202610/02102026.xml' => Http::response(load_fixture('02102026.xml')),
    ]);

    $sheet = app(ExchangeRates::class)->on('2026-10-04');

    expect($sheet->date->format('Y-m-d'))->toBe('2026-10-02');
    Http::assertSentCount(3);
    Http::assertSentInOrder([
        fn (Request $r) => str_ends_with($r->url(), '04102026.xml'),
        fn (Request $r) => str_ends_with($r->url(), '03102026.xml'),
        fn (Request $r) => str_ends_with($r->url(), '02102026.xml'),
    ]);
});

it('crosses a month boundary when falling back', function () {
    Carbon::setTestNow('2026-10-09 12:00:00');
    Http::fake([
        BASE.'/202610/01102026.xml' => notFound(),
        BASE.'/202609/30092026.xml' => Http::response(str_replace('02.10.2026', '30.09.2026', load_fixture('02102026.xml'))),
    ]);

    expect(app(ExchangeRates::class)->on('2026-10-01')->date->format('Y-m-d'))->toBe('2026-09-30');
});

it('remembers both the misses and the hit for past dates', function () {
    Carbon::setTestNow('2026-10-09 12:00:00');
    Http::fake([
        BASE.'/202610/04102026.xml' => notFound(),
        BASE.'/202610/03102026.xml' => notFound(),
        BASE.'/202610/02102026.xml' => Http::response(load_fixture('02102026.xml')),
    ]);

    $rates = app(ExchangeRates::class);
    $rates->on('2026-10-04');
    $rates->on('2026-10-04');
    $rates->on('2026-10-03');

    Http::assertSentCount(3);
});

it('accepts DateTime objects and keeps their calendar date', function () {
    Carbon::setTestNow('2026-10-09 12:00:00');
    Http::fake([BASE.'/202610/02102026.xml' => Http::response(load_fixture('02102026.xml'))]);

    // 23:30 in New York on the 2nd is already the 3rd in Istanbul; the caller
    // still means the 2nd.
    $date = new DateTimeImmutable('2026-10-02 23:30:00', new DateTimeZone('America/New_York'));

    expect(app(ExchangeRates::class)->on($date)->date->format('Y-m-d'))->toBe('2026-10-02');
});

it('does not cache a miss for today because the bulletin may still come', function () {
    Carbon::setTestNow('2026-10-02 10:00:00');
    Http::fake([
        BASE.'/202610/02102026.xml' => Http::sequence()
            ->pushResponse(notFound())
            ->push(load_fixture('02102026.xml')),
        BASE.'/202610/01102026.xml' => Http::response(str_replace('02.10.2026', '01.10.2026', load_fixture('02102026.xml'))),
    ]);

    $rates = app(ExchangeRates::class);

    expect($rates->on('2026-10-02')->date->format('Y-m-d'))->toBe('2026-10-01');

    Carbon::setTestNow('2026-10-02 15:45:00');

    expect($rates->on('2026-10-02')->date->format('Y-m-d'))->toBe('2026-10-02');
});

it('gives up after the lookback window', function () {
    Carbon::setTestNow('2026-10-09 12:00:00');
    config(['turkiye.tcmb.max_lookback_days' => 3]);
    app()->forgetInstance(ExchangeRates::class);
    Http::fake([BASE.'/*' => notFound()]);

    expect(fn () => app(ExchangeRates::class)->on('2026-10-04'))
        ->toThrow(TcmbException::class, 'No TCMB bulletin found for 2026-10-04 or the 3 days before it.');

    Http::assertSentCount(4);
});

it('refuses future dates without a request', function () {
    Carbon::setTestNow('2026-10-09 12:00:00');
    Http::fake();

    expect(fn () => app(ExchangeRates::class)->on('2026-10-10'))->toThrow(InvalidArgumentException::class);
    Http::assertNothingSent();
});

it('refuses dates it cannot parse', function () {
    app(ExchangeRates::class)->on('not a date');
})->throws(InvalidArgumentException::class);

it('wraps a timeout in a TcmbException', function () {
    Http::fake(fn () => throw new ConnectionException('cURL error 28: Operation timed out after 10001 milliseconds'));

    expect(fn () => app(ExchangeRates::class)->today())
        ->toThrow(TcmbException::class, 'Operation timed out');
});

it('retries a connection error before giving up', function () {
    config(['turkiye.tcmb.retries' => 2]);
    app()->forgetInstance(ExchangeRates::class);

    $attempts = 0;
    Http::fake(function () use (&$attempts) {
        $attempts++;

        if ($attempts < 3) {
            throw new ConnectionException('cURL error 7: Failed to connect');
        }

        return Http::response(load_fixture('today.xml'));
    });

    expect(app(ExchangeRates::class)->today()->bulletinNo)->toBe('2026/190')
        ->and($attempts)->toBe(3);
});

it('does not retry or skip a day on a server error', function () {
    Carbon::setTestNow('2026-10-09 12:00:00');
    Http::fake([BASE.'/*' => Http::response('Service Unavailable', 503)]);

    expect(fn () => app(ExchangeRates::class)->on('2026-10-04'))
        ->toThrow(TcmbException::class, 'HTTP 503');

    Http::assertSentCount(1);
});

it('throws on a broken bulletin and does not cache it', function () {
    Http::fake([BASE.'/today.xml' => Http::sequence()
        ->push(substr(load_fixture('today.xml'), 0, 700))
        ->push(load_fixture('today.xml')),
    ]);

    $rates = app(ExchangeRates::class);

    expect(fn () => $rates->today())->toThrow(TcmbException::class, 'unreadable bulletin');
    expect($rates->today()->bulletinNo)->toBe('2026/190');
});

it('treats a missing today.xml as an error', function () {
    Http::fake([BASE.'/today.xml' => notFound()]);

    app(ExchangeRates::class)->today();
})->throws(TcmbException::class, 'HTTP 404');

it('gets a single rate through the facade', function () {
    Carbon::setTestNow('2026-10-09 12:00:00');
    Http::fake([
        BASE.'/today.xml' => Http::response(load_fixture('today.xml')),
        BASE.'/202610/02102026.xml' => Http::response(load_fixture('02102026.xml')),
    ]);

    expect(Tcmb::rate('eur')->forexBuying)->toBe(54.9548)
        ->and(Tcmb::rate('USD', '2026-10-02')->forexSelling)->toBe(49.0582);
});

it('uses the configured base url and cache store', function () {
    config([
        'turkiye.tcmb.base_url' => 'https://mirror.example.test/kurlar/',
        'turkiye.tcmb.cache.store' => 'file',
    ]);
    app()->forgetInstance(ExchangeRates::class);
    Cache::store('file')->forget('turkiye.tcmb.today');

    Http::fake(['https://mirror.example.test/kurlar/today.xml' => Http::response(load_fixture('today.xml'))]);

    app(ExchangeRates::class)->today();

    expect(Cache::store('file')->get('turkiye.tcmb.today'))->toBeString()
        ->and(Cache::store('array')->get('turkiye.tcmb.today'))->toBeNull();

    Cache::store('file')->forget('turkiye.tcmb.today');
});
