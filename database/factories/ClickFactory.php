<?php

namespace Database\Factories;

use App\Models\Click;
use App\Models\Link;
use App\Models\Service;
use App\Models\Url;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Click>
 */
class ClickFactory extends Factory
{
    protected $model = Click::class;

    /**
     * Service, link and url are independent by default (all FK-valid). When a
     * test needs the click's service to match its link, wire both explicitly:
     * `Click::factory()->for($link)->create(['service_id' => $link->service_id])`.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => fake()->uuid(),
            'service_id' => Service::factory(),
            'link_id' => Link::factory(),
            'url_id' => Url::factory(),
            'referrer_id' => null,
            'user_agent_id' => null,
            'ip_address_id' => null,
        ];
    }
}
