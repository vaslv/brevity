<?php

namespace App\Services\Links\Conditions;

use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

abstract class AbstractConditionHandler implements ConditionHandler
{
    public static function rules(): array
    {
        return [];
    }

    public static function type(): string
    {
        return Str::of(static::class)
            ->classBasename()
            ->before('ConditionHandler')
            ->snake();
    }

    public static function validate(array $data): array
    {
        $validator = Validator::make($data, static::rules());

        return $validator->validate();
    }
}
