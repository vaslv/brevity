<?php

namespace Database\Factories;

use App\Models\Condition;
use App\Models\Link;
use App\Models\Rule;
use App\Models\Url;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Rule>
 */
class RuleFactory extends Factory
{
    protected $model = Rule::class;

    /**
     * An unconditional, direct-mode rule at priority 1. When attaching several
     * rules to one link, give each a distinct priority (a `links_priority`
     * unique index is enforced) via priority().
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'link_id' => Link::factory(),
            'url_id' => Url::factory(),
            'condition_id' => null,
            'transition_mode' => null,
            'priority' => 1,
        ];
    }

    public function priority(int $priority): static
    {
        return $this->state(['priority' => $priority]);
    }

    public function transitionMode(?string $mode): static
    {
        return $this->state(['transition_mode' => $mode]);
    }

    /**
     * Attach a condition: an existing model, a factory, or a fresh default one.
     */
    public function withCondition(Condition|Factory|null $condition = null): static
    {
        return $this->state([
            'condition_id' => $condition instanceof Condition ? $condition->id : ($condition ?? Condition::factory()),
        ]);
    }
}
