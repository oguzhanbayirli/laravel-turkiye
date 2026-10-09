<?php

use OguzhanBayirli\Turkiye\Identity\Vkn;

it('accepts numbers with a correct check digit', function (string $vkn) {
    expect(Vkn::isValid($vkn))->toBeTrue();
})->with([
    // The example used in python-stdnum's documentation.
    '4540536920',
    '5425043585',
    '4856568270',
    '7973741741',
    '1111111114',
    '9999999994',
    '0200000006',
    // First digit hits the "multiple of 9 counts as 9" rule, the second
    // digit hits the "zero step is skipped" rule.
    '0234567891',
]);

it('rejects every other check digit', function (string $vkn) {
    $first9 = substr($vkn, 0, 9);

    foreach (range(0, 9) as $digit) {
        if ((string) $digit !== $vkn[9]) {
            expect(Vkn::isValid($first9.$digit))->toBeFalse();
        }
    }
})->with(['4540536920', '0234567891', '9999999994']);

it('rejects the wrong length or non-digits', function (mixed $value) {
    expect(Vkn::isValid($value))->toBeFalse();
})->with([
    'nine digits' => '454053692',
    'eleven digits' => '45405369200',
    'a TCKN' => '10000000146',
    'letters' => '454053692O',
    'empty' => '',
    'null' => null,
    'bool' => true,
]);

it('accepts integers and trims whitespace', function () {
    expect(Vkn::isValid(4540536920))->toBeTrue()
        ->and(Vkn::isValid(' 4540536920 '))->toBeTrue();
});

it('generates numbers that pass its own check', function () {
    foreach (range(1, 200) as $_) {
        expect(Vkn::isValid(Vkn::generate()))->toBeTrue();
    }
});
