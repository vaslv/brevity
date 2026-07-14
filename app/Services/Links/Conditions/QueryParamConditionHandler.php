<?php

namespace App\Services\Links\Conditions;

use App\Models\Condition;
use App\Services\Links\QueryString;

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

        // Match the raw query string, not $request->query(): PHP's query bag
        // mangles a dotted/spaced key (sub.id -> sub_id) so such a condition
        // could never fire (r44). Array params (?key[]=x) and absent params
        // still never equal a scalar value.
        $params = QueryString::parse($context->request->getQueryString());

        return ($params[$key] ?? null) === $value;
    }

    public static function rules(): array
    {
        return [
            'key' => ['required', 'string', 'max:255'],
            'value' => ['required', 'string', 'max:255'],
        ];
    }
}
