<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\Link;
use App\Models\Rule;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DomainIdnTest extends TestCase
{
    use RefreshDatabase;

    private const string ASCII = 'xn--e1afmkfd.xn--p1ai';

    public static function domainInputs(): array
    {
        return [['пример.рф'], [self::ASCII], [' ПРИМЕР.РФ. ']];
    }

    public function test_a_different_allowed_domain_cannot_resolve_the_link(): void
    {
        $domain = Domain::factory()->create(['value' => 'пример.рф']);
        $link = Link::factory()->forDomain($domain)->create();
        Rule::factory()->for($link)->create();

        $this->get(self::SHORT_LINK_HOST.'/'.$link->code)->assertNotFound();
    }

    #[DataProvider('domainInputs')]
    public function test_api_accepts_both_forms_and_links_resolve(string $input): void
    {
        Queue::fake();
        $domain = Domain::factory()->create(['value' => 'пример.рф']);
        config(['app.hosts' => ['localhost', 'ПРИМЕР.РФ.']]);
        $service = Service::factory()->create();
        $token = $service->createToken('test', ['links:create'])->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/links', [
            'domain' => $input,
            'rules' => [['url' => 'https://example.com/target']],
        ])->assertCreated()
            ->assertJsonPath('data.domain', self::ASCII)
            ->assertJsonPath('data.display_domain', 'пример.рф');

        $link = Link::query()->where('code', $response->json('data.code'))->firstOrFail();
        $this->assertSame($domain->id, $link->domain_id);
        $this->assertSame('https://'.self::ASCII.'/'.$link->code, $response->json('data.url'));
        $this->get($response->json('data.url'))->assertRedirect('https://example.com/target');
        $this->get('https://unknown.test/'.$link->code)->assertNotFound();

        $this->withToken($token)->getJson('http://localhost/api/domains')
            ->assertOk()->assertJsonPath('data.0.domain', self::ASCII)
            ->assertJsonPath('data.0.display_domain', 'пример.рф');
    }

    public function test_api_rejects_invalid_and_unknown_domains(): void
    {
        $service = Service::factory()->create();
        $token = $service->createToken('test', ['links:create'])->plainTextToken;

        foreach (['https://пример.рф', 'xn--a.test', 'неизвестный.рф', ['пример.рф']] as $domain) {
            $this->withToken($token)->postJson('/api/links', [
                'domain' => $domain,
                'rules' => [['url' => 'https://example.com/target']],
            ])->assertUnprocessable()->assertJsonValidationErrors('domain');
        }

        $this->assertSame(0, Link::query()->count());
    }

    public function test_model_stores_ascii_and_exposes_unicode(): void
    {
        $domain = Domain::factory()->create(['value' => 'ПРИМЕР.РФ.']);
        $this->assertSame(self::ASCII, $domain->refresh()->value);
        $this->assertSame('пример.рф', $domain->display_domain);
        $this->assertSame('https://'.self::ASCII, $domain->url);

        $domain->update(['value' => 'BÜCHER.DE']);
        $this->assertSame('xn--bcher-kva.de', $domain->refresh()->value);
    }

    public function test_search_accepts_complete_unicode_and_ascii_names(): void
    {
        $domain = Domain::factory()->create(['value' => 'пример.рф']);
        Domain::factory()->create(['value' => 'other.test']);

        foreach (['ПРИМЕР.РФ', self::ASCII, 'e1afmkfd'] as $search) {
            $this->assertSame([$domain->id], Domain::query()->matchingName($search)->pluck('id')->all());
        }
    }

    public function test_sync_deduplicates_forms_and_skips_idn_technical_host(): void
    {
        $domain = Domain::factory()->asDefault()->create(['value' => 'пример.рф']);
        config([
            'app.technical_host' => 'bücher.de',
            'app.hosts' => ['xn--bcher-kva.de', 'ПРИМЕР.РФ', self::ASCII, 'новый.рф'],
        ]);
        $this->artisan('domains:sync')->assertSuccessful();
        $this->artisan('domains:sync')->assertSuccessful();
        $this->assertSame(2, Domain::query()->count());
        $this->assertTrue($domain->refresh()->is_default);
        $this->assertFalse(Domain::query()->where('value', 'xn--bcher-kva.de')->exists());
        $this->get('http://xn--bcher-kva.de/login')->assertOk();
        $this->get('http://'.self::ASCII.'/login')->assertNotFound();
    }

    public function test_sync_preflights_invalid_configuration_before_creating_domains(): void
    {
        config(['app.hosts' => ['valid.test', 'https://invalid.test']]);

        try {
            $this->artisan('domains:sync')->run();
            $this->fail('Invalid hosts must not be imported.');
        } catch (\InvalidArgumentException) {
            $this->assertSame(0, Domain::query()->count());
        }
    }
}
