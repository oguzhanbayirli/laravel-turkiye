<?php

declare(strict_types=1);

namespace OguzhanBayirli\Turkiye\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use OguzhanBayirli\Turkiye\Identity\Tckn as Checker;

final class Tckn implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! Checker::isValid($value)) {
            $fail('turkiye::validation.tckn')->translate();
        }
    }
}
