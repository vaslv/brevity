<?php

namespace App\Rules;

use App\Services\Links\Domains\DomainName;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class ValidDomain implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || DomainName::tryToAscii($value) === null) {
            $fail('resources/domain.validation.invalid')->translate();
        }
    }
}
