<?php

namespace App\Services\Links\Conditions;

use App\Models\Condition;

interface ConditionHandler
{
    public function matches(Condition $condition, ConditionContext $context): bool;

    public static function rules(): array;

    public static function type(): string;

    public static function validate(array $data): array;
}
