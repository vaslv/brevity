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
            ->with('url', 'conditions')
            ->get();

        foreach ($rules as $rule) {
            if ($this->ruleMatches($rule, $context, $link)) {
                return $rule;
            }
        }

        return null;
    }

    /**
     * AND semantics (RUL-01): a rule wins only when EVERY one of its conditions
     * matches; a rule with no conditions is unconditional. An unknown condition
     * type fails closed — the whole rule is skipped, never silently matched.
     */
    private function ruleMatches(Rule $rule, ConditionContext $context, Link $link): bool
    {
        foreach ($rule->conditions as $condition) {
            $handler = $this->registry->getHandlerFor($condition);

            if (! $handler) {
                report(new \RuntimeException(
                    sprintf('Unknown condition type "%s" on link #%d', $condition->type, $link->id)
                ));

                return false;
            }

            if (! $handler->matches($condition, $context)) {
                return false;
            }
        }

        return true;
    }
}
