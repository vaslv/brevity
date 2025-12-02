<?php

namespace App\Services\Links\Conditions;

use App\Models\Condition;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

final class TimeBeforeConditionHandler extends AbstractConditionHandler
{
    public function matches(Condition $condition, ConditionContext $context): bool
    {
        try {
            $data = self::validate($condition->data);
        } catch (ValidationException $exception) {
            report($exception);

            return false;
        }

        $before = CarbonImmutable::parse($data['before']);

        return $context->now->lessThan($before);
    }

    public static function rules(): array
    {
        return [
            'before' => ['required', 'date_format:Y-m-d\TH:i:sP'],
        ];
    }
}
