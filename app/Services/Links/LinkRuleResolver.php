<?php

namespace App\Services\Links;

use App\Models\Link;
use App\Models\Rule;
use App\Services\Links\Conditions\ConditionContext;
use App\Services\Links\Conditions\ConditionRegistry;

readonly class LinkRuleResolver
{
    public function __construct(
        private ConditionRegistry $registry
    ) {}

    public function resolve(Link $link, ConditionContext $context): ?Rule
    {
        /** @var Rule[] $rules */
        $rules = $link->rules()
            ->with('url', 'condition')
            ->get();

        foreach ($rules as $rule) {
            if ($rule->condition === null) {
                return $rule;
            }

            $condition = $rule->condition;
            $handler = $this->registry->getHandlerFor($condition);

            if (! $handler) {
                report(new \RuntimeException(
                    sprintf('Unknown condition type "%s" on link #%d', $condition->type, $link->id)
                ));

                continue;
            }

            if ($handler->matches($condition, $context)) {
                return $rule;
            }
        }

        return null;
    }
}
