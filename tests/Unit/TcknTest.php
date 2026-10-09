<?php

use OguzhanBayirli\Turkiye\Identity\Tckn;

// None of these belong to a real person as far as anyone knows: they were
// produced with the check digit algorithm. 10000000146 is the usual
// placeholder in Turkish test data.

it('accepts numbers with correct check digits', function (string $tckn) {
    expect(Tckn::isValid($tckn))->toBeTrue();
})->with([
    '10000000146',
    '72367126228',
    '30924255088',
    '80448265490',
    'tenth digit from a negative intermediate' => '19090909018',
]);

it('rejects a wrong tenth digit', function () {
    expect(Tckn::isValid('10000000156'))->toBeFalse();
});

it('rejects a wrong eleventh digit', function () {
    expect(Tckn::isValid('10000000147'))->toBeFalse()
        ->and(Tckn::isValid('72367126229'))->toBeFalse();
});

it('rejects a leading zero even when the check digits work out', function () {
    // 012345678 followed by its computed check digits 4 and 0.
    expect(Tckn::isValid('01234567840'))->toBeFalse();
});

it('rejects the wrong length or non-digits', function (mixed $value) {
    expect(Tckn::isValid($value))->toBeFalse();
})->with([
    'ten digits' => '1000000014',
    'twelve digits' => '100000001460',
    'letters' => '1000000014a',
    'inner space' => '100000 00146',
    'dashes' => '100-000-00146',
    'empty' => '',
    'null' => null,
    'float' => 10000000146.0,
    'array' => [['10000000146']],
    'unicode digits' => '١٠٠٠٠٠٠٠١٤٦',
]);

it('accepts integers and trims surrounding whitespace', function () {
    expect(Tckn::isValid(10000000146))->toBeTrue()
        ->and(Tckn::isValid(" 10000000146\n"))->toBeTrue();
});

it('generates numbers that pass its own check', function () {
    foreach (range(1, 200) as $_) {
        $tckn = Tckn::generate();

        expect($tckn)->toMatch('/^[1-9][0-9]{10}$/')
            ->and(Tckn::isValid($tckn))->toBeTrue();
    }
});
