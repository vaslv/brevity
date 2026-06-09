<?php

namespace Database\Factories;

use App\Models\Domain;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Domain>
 */
class DomainFactory extends Factory
{
    protected $model = Domain::class;

    /**
     * The fallback domain used when a link is created without an explicit one.
     */
    public function asDefault(): static
    {
        return $this->state(['is_default' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'value' => fake()->unique()->domainName(),
            'is_default' => false,
        ];
    }
}
