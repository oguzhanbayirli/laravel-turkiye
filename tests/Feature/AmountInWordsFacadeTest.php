<?php

use OguzhanBayirli\Turkiye\Facades\AmountInWords;
use OguzhanBayirli\Turkiye\Words\WordsFormat;

it('works through the facade', function () {
    expect(AmountInWords::convert('1234.56'))->toBe('Bin İki Yüz Otuz Dört Türk Lirası Elli Altı Kuruş')
        ->and(AmountInWords::invoice('1234.56'))->toBe('Yalnız #BinİkiYüzOtuzDörtTL,ElliAltıKr#')
        ->and(AmountInWords::convert(99.9, WordsFormat::invoiceUpper()))->toBe('YALNIZ: DOKSAN DOKUZ TÜRK LİRASI DOKSAN KURUŞ');
});

it('registers the publishable config and translations', function () {
    expect(config('turkiye.tcmb.base_url'))->toBe('https://www.tcmb.gov.tr/kurlar')
        ->and(trans('turkiye::validation.vkn', ['attribute' => 'x'], 'tr'))->toBe('x geçerli bir vergi kimlik numarası olmalıdır.');
});
