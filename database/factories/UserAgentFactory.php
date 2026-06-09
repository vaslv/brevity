<?php

namespace Database\Factories;

use App\Models\UserAgent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserAgent>
 */
class UserAgentFactory extends Factory
{
    protected $model = UserAgent::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'value' => fake()->unique()->userAgent(),
        ];
    }
}
