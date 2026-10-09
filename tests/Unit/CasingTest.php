<?php

use OguzhanBayirli\Turkiye\Words\Casing;

it('handles the dotted and dotless i', function () {
    expect(Casing::Upper->apply('iki ılık'))->toBe('İKİ ILIK')
        ->and(Casing::Lower->apply('İKİ ILIK'))->toBe('iki ılık')
        ->and(Casing::Title->apply('iki'))->toBe('İki')
        ->and(Casing::Title->apply('ılık'))->toBe('Ilık')
        ->and(Casing::Title->apply('ÜÇ'))->toBe('Üç');
});
