<?php

declare(strict_types=1);

namespace OguzhanBayirli\Turkiye\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use OguzhanBayirli\Turkiye\Identity\Iban as Checker;

final class TurkishIban implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! Checker::isValid($value)) {
            $fail('turkiye::validation.tr_iban')->translate();
        }
    }
}
