<?php

namespace Tests\Feature\Api;

use App\Models\Domain;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class DefaultDomainTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_link_created_without_a_domain_uses_the_default_domain(): void
    {
        Domain::query()->create(['value' => 'other.example.com']);
        Domain::query()->create(['value' => 'default.example.com', 'is_default' => true]);

        $response = $this->postLink([
            'rules' => [['url' => 'https://example.com/landing']],
        ]);

        $code = $response->assertCreated()->json('data.code');

        $response
            ->assertJsonPath('data.domain', 'default.example.com')
            ->assertJsonPath('data.url', 'https://default.example.com/'.$code);
    }

    public function test_a_link_without_a_domain_falls_back_to_app_url_when_no_default_exists(): void
    {
        Domain::query()->create(['value' => 'other.example.com']);

        $response = $this->postLink([
            'rules' => [['url' => 'https://example.com/landing']],
        ]);

        $code = $response->assertCreated()->json('data.code');

        $response
            ->assertJsonPath('data.domain', null)
            ->assertJsonPath('data.url', rtrim((string) config('app.url'), '/').'/'.$code);
    }

    public function test_an_explicit_domain_overrides_the_default(): void
    {
        Domain::query()->create(['value' => 'default.example.com', 'is_default' => true]);
        Domain::query()->create(['value' => 'explicit.example.com']);

        $response = $this->postLink([
            'domain' => 'explicit.example.com',
            'rules' => [['url' => 'https://example.com/landing']],
        ]);

        $code = $response->assertCreated()->json('data.code');

        $response
            ->assertJsonPath('data.domain', 'explicit.example.com')
            ->assertJsonPath('data.url', 'https://explicit.example.com/'.$code);
    }

    public function test_promoting_a_domain_demotes_the_previous_default(): void
    {
        $first = Domain::query()->create(['value' => 'first.example.com', 'is_default' => true]);
        $second = Domain::query()->create(['value' => 'second.example.com', 'is_default' => true]);

        $this->assertFalse($first->fresh()->is_default);
        $this->assertTrue($second->fresh()->is_default);
        $this->assertSame($second->id, Domain::default()?->id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function postLink(array $data): TestResponse
    {
        $service = Service::query()->create([
            'name' => 'Service '.fake()->unique()->word(),
        ]);

        $token = $service->createToken('test')->plainTextToken;

        return $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/links', $data);
    }
}
