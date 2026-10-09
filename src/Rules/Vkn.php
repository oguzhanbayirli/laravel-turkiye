<?php

declare(strict_types=1);

namespace OguzhanBayirli\Turkiye\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use OguzhanBayirli\Turkiye\Identity\Vkn as Checker;

final class Vkn implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! Checker::isValid($value)) {
            $fail('turkiye::validation.vkn')->translate();
        }
    }
}
