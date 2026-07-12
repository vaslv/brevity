<?php

namespace App\Services\Links\Conditions;

use App\Models\Condition;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * Matches once the current moment is at or after the stored date — the mirror
 * of TimeBeforeConditionHandler. Together they express a scheduled window:
 * after_date opens it, time_before closes it, with no gap at the shared edge
 * (after_date is inclusive `>=`, time_before is exclusive `<`).
 */
final class AfterDateConditionHandler extends AbstractConditionHandler
{
    public function matches(Condition $condition, ConditionContext $context): bool
    {
        // Read the stored value directly (validated at write time); fail closed
        // on corrupted data instead of re-validating on every resolve.
        $after = $condition->data['after'] ?? null;

        if (! is_string($after)) {
            return false;
        }

        try {
            $afterTime = CarbonImmutable::parse($after);
        } catch (Throwable) {
            return false;
        }

        return $context->now->greaterThanOrEqualTo($afterTime);
    }

    public static function rules(): array
    {
        return [
            'after' => ['required', 'date_format:Y-m-d\TH:i:sP'],
        ];
    }
}
