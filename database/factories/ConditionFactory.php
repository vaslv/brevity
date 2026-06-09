<?php

namespace Database\Factories;

use App\Models\Condition;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Condition>
 */
class ConditionFactory extends Factory
{
    protected $model = Condition::class;

    /**
     * Defaults to a `time_before` condition with a far-future cutoff, so it
     * matches under a normal "now".
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => 'time_before',
            'data' => ['before' => $this->formatCutoff(fake()->dateTimeBetween('+1 year', '+5 years'))],
        ];
    }

    /**
     * A `time_before` cutoff already in the past, so the condition never matches.
     */
    public function expired(): static
    {
        return $this->state(['data' => ['before' => $this->formatCutoff(fake()->dateTimeBetween('-5 years', '-1 day'))]]);
    }

    public function timeBefore(string $before): static
    {
        return $this->state(['data' => ['before' => CarbonImmutable::parse($before)->format('Y-m-d\TH:i:sP')]]);
    }

    private function formatCutoff(\DateTimeInterface $cutoff): string
    {
        return CarbonImmutable::instance($cutoff)->format('Y-m-d\TH:i:sP');
    }
}
