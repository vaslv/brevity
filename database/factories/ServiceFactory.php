<?php

namespace Database\Factories;

use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    protected $model = Service::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Service '.fake()->unique()->words(2, true),
            'callback_url' => null,
        ];
    }

    /**
     * A public IP keeps the callback URL on the right side of the SSRF guard.
     */
    public function withCallbackUrl(string $url = 'https://93.184.216.34/hook'): static
    {
        return $this->state(['callback_url' => $url]);
    }
}
