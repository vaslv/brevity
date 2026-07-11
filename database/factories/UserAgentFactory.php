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

    public function bot(): static
    {
        return $this->state(fn (): array => [
            'value' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
            'is_bot' => true,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'value' => fake()->unique()->userAgent(),
            'is_bot' => false,
        ];
    }
}
