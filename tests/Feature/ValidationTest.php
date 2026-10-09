<?php

use Illuminate\Support\Facades\Validator;
use OguzhanBayirli\Turkiye\Rules\Tckn;
use OguzhanBayirli\Turkiye\Rules\TcknOrVkn;
use OguzhanBayirli\Turkiye\Rules\TurkishIban;
use OguzhanBayirli\Turkiye\Rules\Vkn;

$valid = [
    'tckn' => '10000000146',
    'vkn' => '4540536920',
    'iban' => 'TR33 0006 1005 1978 6457 8413 26',
];

it('passes valid values with rule objects', function () use ($valid) {
    $validator = Validator::make($valid + ['buyer' => '4540536920'], [
        'tckn' => ['required', new Tckn],
        'vkn' => ['required', new Vkn],
        'iban' => ['required', new TurkishIban],
        'buyer' => ['required', new TcknOrVkn],
    ]);

    expect($validator->passes())->toBeTrue();
});

it('passes valid values with string rules', function () use ($valid) {
    $validator = Validator::make($valid + ['buyer' => '10000000146'], [
        'tckn' => 'required|tckn',
        'vkn' => 'required|vkn',
        'iban' => 'required|tr_iban',
        'buyer' => 'required|tckn_or_vkn',
    ]);

    expect($validator->passes())->toBeTrue();
});

it('fails invalid values with English messages', function (array|string $rule, string $field, string $message) {
    app()->setLocale('en');

    $validator = Validator::make([$field => 'nope'], [$field => $rule]);

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->first($field))->toBe($message);
})->with([
    'tckn object' => [[new Tckn], 'national_id', 'The national id field must be a valid Turkish identity number.'],
    'tckn string' => ['tckn', 'national_id', 'The national id field must be a valid Turkish identity number.'],
    'vkn object' => [[new Vkn], 'tax_number', 'The tax number field must be a valid Turkish tax number.'],
    'vkn string' => ['vkn', 'tax_number', 'The tax number field must be a valid Turkish tax number.'],
    'iban object' => [[new TurkishIban], 'iban', 'The iban field must be a valid Turkish IBAN.'],
    'iban string' => ['tr_iban', 'iban', 'The iban field must be a valid Turkish IBAN.'],
    'either object' => [[new TcknOrVkn], 'buyer', 'The buyer field must be a valid Turkish identity or tax number.'],
    'either string' => ['tckn_or_vkn', 'buyer', 'The buyer field must be a valid Turkish identity or tax number.'],
]);

it('fails invalid values with Turkish messages', function (array|string $rule, string $message) {
    app()->setLocale('tr');

    $validator = Validator::make(['no' => '12345678901'], ['no' => $rule]);

    expect($validator->errors()->first('no'))->toBe($message);
})->with([
    [[new Tckn], 'no geçerli bir T.C. kimlik numarası olmalıdır.'],
    ['tckn', 'no geçerli bir T.C. kimlik numarası olmalıdır.'],
    ['vkn', 'no geçerli bir vergi kimlik numarası olmalıdır.'],
    [[new TurkishIban], 'no geçerli bir TR IBAN olmalıdır.'],
    ['tckn_or_vkn', 'no geçerli bir T.C. kimlik veya vergi kimlik numarası olmalıdır.'],
]);

it('uses custom attribute names', function () {
    app()->setLocale('tr');

    $validator = Validator::make(['tc' => '1'], ['tc' => 'tckn'], [], ['tc' => 'T.C. kimlik no']);

    expect($validator->errors()->first('tc'))->toBe('T.C. kimlik no geçerli bir T.C. kimlik numarası olmalıdır.');
});

it('lets the application override the message', function () {
    app()->setLocale('en');
    app('translator')->addLines(['validation.tckn' => 'Check the :attribute again.'], 'en');

    $validator = Validator::make(['tc' => '1'], ['tc' => 'tckn']);

    expect($validator->errors()->first('tc'))->toBe('Check the tc again.');
});

it('respects inline custom messages', function () {
    $validator = Validator::make(['tc' => '1'], ['tc' => 'tckn'], ['tc.tckn' => 'Bad TC.']);

    expect($validator->errors()->first('tc'))->toBe('Bad TC.');
});

it('skips empty optional fields like the built-in rules do', function () {
    $validator = Validator::make(['tc' => '', 'iban' => null], [
        'tc' => 'nullable|tckn',
        'iban' => ['nullable', new TurkishIban],
    ]);

    expect($validator->passes())->toBeTrue();
});
