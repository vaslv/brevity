<?php

namespace Database\Factories;

use App\Models\Domain;
use App\Models\Link;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Link>
 *
 * `code` is intentionally left unset: the Link model's `created` hook generates
 * it from the row id. Read `$link->code` after creating.
 */
class LinkFactory extends Factory
{
    protected $model = Link::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'service_id' => Service::factory(),
            'domain_id' => null,
            'title' => fake()->sentence(3),
            'forward_query' => false,
            'callback_data' => null,
        ];
    }

    public function forDomain(Domain $domain): static
    {
        return $this->state(['domain_id' => $domain->id]);
    }

    public function forwardingQuery(): static
    {
        return $this->state(['forward_query' => true]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function withCallbackData(array $data = ['campaign_id' => 'cmp-1']): static
    {
        return $this->state(['callback_data' => $data]);
    }
}
