<?php

namespace App\Services\Links\Conditions;

use App\Models\Condition;

/**
 * Matches when the visit's query string carries an exact `key=value` pair —
 * e.g. route `?partner=acme` traffic to a dedicated landing. Compares against
 * the raw request query, independent of the link's forward_query setting.
 */
final class QueryParamConditionHandler extends AbstractConditionHandler
{
    public function matches(Condition $condition, ConditionContext $context): bool
    {
        $key = $condition->data['key'] ?? null;
        $value = $condition->data['value'] ?? null;

        if (! is_string($key) || ! is_string($value)) {
            return false;
        }

        // Array params (?key[]=x) and absent params never equal a scalar value.
        return $context->request->query($key) === $value;
    }

    public static function rules(): array
    {
        return [
            'key' => ['required', 'string', 'max:255'],
            'value' => ['required', 'string', 'max:255'],
        ];
    }
}
