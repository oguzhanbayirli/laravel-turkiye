<?php

use OguzhanBayirli\Turkiye\Words\NumberToWords;

it('spells numbers the way TDK writes them', function (int|string $number, string $expected) {
    expect(NumberToWords::spell($number))->toBe($expected);
})->with([
    [0, 'sıfır'],
    [1, 'bir'],
    [6, 'altı'],
    [10, 'on'],
    [11, 'on bir'],
    [40, 'kırk'],
    [99, 'doksan dokuz'],
    [100, 'yüz'],
    [101, 'yüz bir'],
    [110, 'yüz on'],
    [200, 'iki yüz'],
    [999, 'dokuz yüz doksan dokuz'],
    [1000, 'bin'],
    [1001, 'bin bir'],
    [1100, 'bin yüz'],
    [1234, 'bin iki yüz otuz dört'],
    [2000, 'iki bin'],
    [10000, 'on bin'],
    [11000, 'on bir bin'],
    [100000, 'yüz bin'],
    [101000, 'yüz bir bin'],
    [999999, 'dokuz yüz doksan dokuz bin dokuz yüz doksan dokuz'],
    [1000000, 'bir milyon'],
    [1001000, 'bir milyon bin'],
    [1000001, 'bir milyon bir'],
    [2500000, 'iki milyon beş yüz bin'],
    [1000000000, 'bir milyar'],
    [1000000000000, 'bir trilyon'],
    [1000000000000000, 'bir katrilyon'],
    [PHP_INT_MAX, 'dokuz kentilyon iki yüz yirmi üç katrilyon üç yüz yetmiş iki trilyon otuz altı milyar sekiz yüz elli dört milyon yedi yüz yetmiş beş bin sekiz yüz yedi'],
    ['1000000000000000000000', 'bir seksilyon'],
    ['999000000000000000000000000000000000', 'dokuz yüz doksan dokuz desilyon'],
    [-15, 'eksi on beş'],
    ['-0', 'sıfır'],
    ['007', 'yedi'],
]);

it('returns the words separately', function () {
    expect(NumberToWords::words(1234))->toBe(['bin', 'iki', 'yüz', 'otuz', 'dört']);
});

it('rejects input that is not a whole number', function (string $value) {
    NumberToWords::spell($value);
})->with(['12.5', '1,000', 'abc', '', '1e6', '--1'])
    ->throws(InvalidArgumentException::class);

it('refuses numbers beyond desilyon', function () {
    NumberToWords::spell('1'.str_repeat('0', 36));
})->throws(InvalidArgumentException::class, 'desilyon');
