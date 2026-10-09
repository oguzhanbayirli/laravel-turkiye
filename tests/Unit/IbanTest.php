<?php

use OguzhanBayirli\Turkiye\Identity\Iban;

it('accepts valid Turkish IBANs in the forms people paste them', function (string $iban) {
    expect(Iban::isValid($iban))->toBeTrue();
})->with([
    'compact' => 'TR330006100519786457841326',
    'grouped' => 'TR33 0006 1005 1978 6457 8413 26',
    'lower case' => 'tr330006100519786457841326',
    'with dashes' => 'TR33-0006-1005-1978-6457-8413-26',
    'surrounding whitespace' => "  TR330006100519786457841326\t",
    'non-breaking spaces' => "TR33\u{00A0}0006\u{00A0}1005\u{00A0}1978\u{00A0}6457\u{00A0}8413\u{00A0}26",
]);

it('rejects a single changed digit', function () {
    expect(Iban::isValid('TR330006100519786457841327'))->toBeFalse()
        ->and(Iban::isValid('TR340006100519786457841326'))->toBeFalse();
});

it('rejects two swapped digits', function () {
    expect(Iban::isValid('TR330006100519786457841362'))->toBeFalse();
});

it('rejects other countries and malformed values', function (mixed $value) {
    expect(Iban::isValid($value))->toBeFalse();
})->with([
    'German IBAN' => 'DE89370400440532013000',
    'too short' => 'TR33000610051978645784132',
    'too long' => 'TR3300061005197864578413260',
    'letter in account part' => 'TR33000610051978645784132A',
    'no country code' => '330006100519786457841326',
    'empty' => '',
    'null' => null,
    'int' => 330006100519786457841326,
]);

it('normalizes and formats', function () {
    expect(Iban::normalize(' tr33 0006-1005 1978 6457 8413 26 '))->toBe('TR330006100519786457841326')
        ->and(Iban::format('tr330006100519786457841326'))->toBe('TR33 0006 1005 1978 6457 8413 26');
});

it('reads the bank code', function () {
    expect(Iban::bankCode('TR33 0006 1005 1978 6457 8413 26'))->toBe('00061')
        ->and(Iban::bankCode('TR330006100519786457841327'))->toBeNull();
});

it('generates valid IBANs for a given bank and account', function () {
    $iban = Iban::generate('00010', '123');

    expect($iban)->toHaveLength(Iban::LENGTH)
        ->and(substr($iban, 4))->toBe('0001000000000000000123')
        ->and(Iban::isValid($iban))->toBeTrue()
        ->and(Iban::bankCode($iban))->toBe('00010');

    foreach (range(1, 200) as $_) {
        expect(Iban::isValid(Iban::generate('00064')))->toBeTrue();
    }
});

it('refuses to generate from bad input', function (string $bank, ?string $account) {
    Iban::generate($bank, $account);
})->with([
    ['0001', null],
    ['00010', '12345678901234567'],
    ['00010', '12a'],
])->throws(InvalidArgumentException::class);
