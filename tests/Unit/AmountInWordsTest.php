<?php

use OguzhanBayirli\Turkiye\Words\AmountInWords;
use OguzhanBayirli\Turkiye\Words\Casing;
use OguzhanBayirli\Turkiye\Words\WordsFormat;

beforeEach(fn () => $this->words = new AmountInWords);

it('writes lira and kuruş', function (int|float|string $amount, string $expected) {
    expect($this->words->convert($amount))->toBe($expected);
})->with([
    ['1234.56', 'Bin İki Yüz Otuz Dört Türk Lirası Elli Altı Kuruş'],
    ['1000', 'Bin Türk Lirası'],
    ['1000.00', 'Bin Türk Lirası'],
    ['0', 'Sıfır Türk Lirası'],
    ['0.00', 'Sıfır Türk Lirası'],
    ['0.5', 'Elli Kuruş'],
    ['0.05', 'Beş Kuruş'],
    ['0.01', 'Bir Kuruş'],
    ['1.01', 'Bir Türk Lirası Bir Kuruş'],
    ['19.99', 'On Dokuz Türk Lirası Doksan Dokuz Kuruş'],
    ['1100', 'Bin Yüz Türk Lirası'],
    ['1001000', 'Bir Milyon Bin Türk Lirası'],
    ['2000000.01', 'İki Milyon Türk Lirası Bir Kuruş'],
    [1500, 'Bin Beş Yüz Türk Lirası'],
    [1234.56, 'Bin İki Yüz Otuz Dört Türk Lirası Elli Altı Kuruş'],
    ['-250.40', 'Eksi İki Yüz Elli Türk Lirası Kırk Kuruş'],
    ['1000000000000', 'Bir Trilyon Türk Lirası'],
]);

it('rounds to the nearest kuruş, half away from zero', function (string $amount, string $expected) {
    expect($this->words->convert($amount))->toBe($expected);
})->with([
    ['10.004', 'On Türk Lirası'],
    ['10.005', 'On Türk Lirası Bir Kuruş'],
    ['10.9949', 'On Türk Lirası Doksan Dokuz Kuruş'],
    ['10.995', 'On Bir Türk Lirası'],
    ['999.995', 'Bin Türk Lirası'],
    ['-12.345', 'Eksi On İki Türk Lirası Otuz Beş Kuruş'],
    ['-0.004', 'Sıfır Türk Lirası'],
    ['99999999999999999999.999', 'Yüz Kentilyon Türk Lirası'],
]);

it('handles floats through two decimal rounding', function () {
    expect($this->words->convert(0.1 + 0.2))->toBe('Otuz Kuruş')
        ->and($this->words->convert(19.999))->toBe('Yirmi Türk Lirası');
});

it('writes the hash-fenced invoice form', function (string $amount, string $expected) {
    expect($this->words->invoice($amount))->toBe($expected);
})->with([
    ['1234.56', 'Yalnız #BinİkiYüzOtuzDörtTL,ElliAltıKr#'],
    ['1000', 'Yalnız #BinTL#'],
    ['0.75', 'Yalnız #YetmişBeşKr#'],
    ['21600.10', 'Yalnız #YirmiBirBinAltıYüzTL,OnKr#'],
]);

it('writes the upper case invoice form with Turkish dotted capitals', function () {
    expect($this->words->convert('1234.56', WordsFormat::invoiceUpper()))
        ->toBe('YALNIZ: BİN İKİ YÜZ OTUZ DÖRT TÜRK LİRASI ELLİ ALTI KURUŞ');
});

it('can show zero kuruş explicitly', function () {
    expect($this->words->convert('1000', WordsFormat::plain()->withZeroKurus()))
        ->toBe('Bin Türk Lirası Sıfır Kuruş')
        ->and($this->words->convert('1000', WordsFormat::invoice()->withZeroKurus()))
        ->toBe('Yalnız #BinTL,SıfırKr#');
});

it('supports lower case and custom labels', function () {
    $format = WordsFormat::plain()
        ->withCasing(Casing::Lower)
        ->withLabels('lira', 'kuruş')
        ->withPrefix('(')
        ->withSuffix(')');

    expect($this->words->convert('61.06', $format))->toBe('(altmış bir lira altı kuruş)');
});

it('keeps labels as written in title case', function () {
    $format = WordsFormat::plain()->withLabels('TL', 'Kr')->withWordSeparator('');

    expect($this->words->convert('41.10', $format))->toBe('KırkBir TL On Kr');
});

it('uses the format given to the constructor as default', function () {
    $words = new AmountInWords(WordsFormat::invoice());

    expect($words->convert('5'))->toBe('Yalnız #BeşTL#');
});

it('rejects amounts it cannot read unambiguously', function (mixed $amount) {
    $this->words->convert($amount);
})->with([
    'turkish thousands' => '1.234,56',
    'comma decimal' => '12,50',
    'thousands separator' => '1,234.56',
    'currency sign' => '₺100',
    'text' => 'yüz',
    'empty' => '',
    'exponent' => '1e3',
    'infinite' => INF,
    'nan' => NAN,
])->throws(InvalidArgumentException::class);
