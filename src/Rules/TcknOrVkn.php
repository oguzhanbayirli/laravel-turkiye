<?php

declare(strict_types=1);

namespace OguzhanBayirli\Turkiye\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use OguzhanBayirli\Turkiye\Identity\Tckn;
use OguzhanBayirli\Turkiye\Identity\Vkn;

/**
 * Invoices accept either: an 11-digit TCKN for individuals or a 10-digit VKN
 * for companies.
 */
final class TcknOrVkn implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! Tckn::isValid($value) && ! Vkn::isValid($value)) {
            $fail('turkiye::validation.tckn_or_vkn')->translate();
        }
    }
}
