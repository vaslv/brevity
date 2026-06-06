<?php

namespace App\Services\Links\Conditions;

use App\Models\Condition;
use Carbon\CarbonImmutable;
use Throwable;

final class TimeBeforeConditionHandler extends AbstractConditionHandler
{
    public function matches(Condition $condition, ConditionContext $context): bool
    {
        // Read the stored value directly — it was validated at write time
        // (LinkCreator/StoreLinkRequest via self::validate). Re-validating on
        // every resolve added Validator overhead to the hot path and, on
        // corrupted data, reported to Sentry on every visit. Fail closed instead.
        $before = $condition->data['before'] ?? null;

        if (! is_string($before)) {
            return false;
        }

        try {
            $beforeTime = CarbonImmutable::parse($before);
        } catch (Throwable) {
            return false;
        }

        return $context->now->lessThan($beforeTime);
    }

    public static function rules(): array
    {
        return [
            'before' => ['required', 'date_format:Y-m-d\TH:i:sP'],
        ];
    }
}
