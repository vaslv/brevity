<?php

namespace Database\Factories;

use App\Models\DomainGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DomainGroup>
 */
class DomainGroupFactory extends Factory
{
    protected $model = DomainGroup::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
        ];
    }
}
