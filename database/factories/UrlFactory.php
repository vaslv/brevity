<?php

namespace Database\Factories;

use App\Models\Url;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Url>
 */
class UrlFactory extends Factory
{
    protected $model = Url::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'value' => 'https://'.fake()->unique()->domainName().'/'.fake()->slug(),
        ];
    }
}
