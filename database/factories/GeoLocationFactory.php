<?php

namespace Database\Factories;

use App\Models\GeoLocation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GeoLocation>
 */
class GeoLocationFactory extends Factory
{
    protected $model = GeoLocation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'country_code' => fake()->countryCode(),
            'region' => fake()->state(),
            'city' => fake()->city(),
        ];
    }
}
