<?php

use OguzhanBayirli\Turkiye\Tcmb\RateSheet;
use OguzhanBayirli\Turkiye\Tcmb\RateType;
use OguzhanBayirli\Turkiye\Tcmb\TcmbException;

// Fixtures/today.xml is TCMB's real bulletin of 08.10.2026, saved unchanged.

it('reads a real TCMB bulletin', function () {
    $sheet = RateSheet::fromXml(load_fixture('today.xml'));

    expect($sheet->date->format('Y-m-d'))->toBe('2026-10-08')
        ->and($sheet->date->getTimezone()->getName())->toBe('Europe/Istanbul')
        ->and($sheet->bulletinNo)->toBe('2026/190')
        ->and($sheet->codes())->toHaveCount(22)
        ->and($sheet->codes())->toContain('USD', 'EUR', 'GBP', 'JPY', 'XDR');
});

it('maps all four prices', function () {
    $usd = RateSheet::fromXml(load_fixture('today.xml'))->get('USD');

    expect($usd->code)->toBe('USD')
        ->and($usd->name)->toBe('US DOLLAR')
        ->and($usd->turkishName)->toBe('ABD DOLARI')
        ->and($usd->unit)->toBe(1)
        ->and($usd->forexBuying)->toBe(49.1267)
        ->and($usd->forexSelling)->toBe(49.2152)
        ->and($usd->banknoteBuying)->toBe(49.0923)
        ->and($usd->banknoteSelling)->toBe(49.2890)
        ->and($usd->get(RateType::BanknoteSelling))->toBe(49.2890);
});

it('looks currencies up case-insensitively', function () {
    $sheet = RateSheet::fromXml(load_fixture('today.xml'));

    expect($sheet->has('eur'))->toBeTrue()
        ->and($sheet->get('eur')->forexBuying)->toBe(54.9548)
        ->and($sheet->has('BTC'))->toBeFalse();
});

it('keeps the unit for JPY and divides it out in perUnit', function () {
    $jpy = RateSheet::fromXml(load_fixture('today.xml'))->get('JPY');

    expect($jpy->unit)->toBe(100)
        ->and($jpy->forexSelling)->toBe(31.1762)
        ->and($jpy->perUnit())->toEqualWithDelta(0.311762, 1e-9)
        ->and($jpy->toLira(10000))->toEqualWithDelta(3117.62, 1e-6);
});

it('converts to lira with the chosen price', function () {
    $usd = RateSheet::fromXml(load_fixture('today.xml'))->get('USD');

    expect($usd->toLira(100))->toEqualWithDelta(4921.52, 1e-6)
        ->and($usd->toLira(100, RateType::ForexBuying))->toEqualWithDelta(4912.67, 1e-6);
});

it('returns null for prices the bulletin leaves empty', function () {
    $sdr = RateSheet::fromXml(load_fixture('today.xml'))->get('XDR');

    expect($sdr->turkishName)->toBe('ÖZEL ÇEKME HAKKI (SDR)')
        ->and($sdr->forexBuying)->toBe(66.4801)
        ->and($sdr->forexSelling)->toBeNull()
        ->and($sdr->banknoteBuying)->toBeNull()
        ->and($sdr->perUnit())->toBeNull();

    $sdr->toLira(1);
})->throws(TcmbException::class, 'no ForexSelling price for [XDR]');

it('throws for a currency that is not listed', function () {
    RateSheet::fromXml(load_fixture('today.xml'))->get('BTC');
})->throws(TcmbException::class, 'Currency [BTC] is not in the TCMB bulletin of 08.10.2026');

it('refuses broken or unexpected documents', function (string $body, string $message) {
    expect(fn () => RateSheet::fromXml($body, 'test'))->toThrow(TcmbException::class, $message);
})->with([
    'empty' => ['', 'empty body'],
    'truncated' => [substr(load_fixture('today.xml'), 0, 600), 'unreadable bulletin'],
    'html error page' => [load_fixture('not-found.html'), 'unreadable bulletin'],
    'other xml' => ['<?xml version="1.0"?><rates/>', 'unexpected root element <rates>'],
    'no date' => ['<Tarih_Date><Currency CurrencyCode="USD"><Unit>1</Unit></Currency></Tarih_Date>', 'Tarih'],
    'no currencies' => ['<Tarih_Date Tarih="08.10.2026" Bulten_No="x"></Tarih_Date>', 'no <Currency> entries'],
]);

it('does not resolve external entities', function () {
    $xml = '<?xml version="1.0"?><!DOCTYPE t [<!ENTITY x SYSTEM "file:///etc/passwd">]>'
        .'<Tarih_Date Tarih="08.10.2026"><Currency CurrencyCode="USD"><Unit>1</Unit><Isim>&x;</Isim>'
        .'<ForexBuying>1</ForexBuying></Currency></Tarih_Date>';

    expect(RateSheet::fromXml($xml)->get('USD')->turkishName)->toBe('');
});
