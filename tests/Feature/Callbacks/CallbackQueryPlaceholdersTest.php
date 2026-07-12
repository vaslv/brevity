<?php

namespace Tests\Feature\Callbacks;

use App\Models\Callback;
use App\Models\Click;
use App\Models\Link;
use App\Models\Rule;
use App\Models\Service;
use App\Models\Url;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Stage 2 of docs/07-plans.md — the visit's query string in clicks and
 * callbacks (GAP-03, docs/03-api.md §10): the click stores its own query
 * string, and {{click.query.<param>}} hands the partner its sub-ids back in
 * the postback. An absent param renders as an empty string; other unknown
 * placeholders stay verbatim.
 */
class CallbackQueryPlaceholdersTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_visit_without_query_renders_empty_strings(): void
    {
        $code = $this->setupLink(['sub' => '{{click.query.sub_id}}']);

        $this->get(static::SHORT_LINK_HOST.'/'.$code)->assertRedirect();

        $this->assertNull(Click::query()->firstOrFail()->visited_query);
        $this->assertSame('', Callback::query()->firstOrFail()->data['sub']);
    }

    public function test_an_oversized_visit_query_is_truncated_not_dropped(): void
    {
        $code = $this->setupLink(['x' => '{{click.id}}']);

        // Over the 2000-byte cap, ending in a percent-encoded multibyte char
        // that the byte cut may split: the click must persist with a truncated
        // query, and the callback must still be created.
        $this->get(static::SHORT_LINK_HOST.'/'.$code.'?q='.str_repeat('a', 2500).'%D0%AF')
            ->assertRedirect();

        $stored = (string) Click::query()->firstOrFail()->visited_query;
        $this->assertLessThanOrEqual(2000, strlen($stored));
        $this->assertStringStartsWith('q=aaa', $stored);
        $this->assertNotNull(Callback::query()->first());
    }

    public function test_array_params_are_skipped_safely(): void
    {
        $code = $this->setupLink(['sub' => '{{click.query.tag}}']);

        $this->get(static::SHORT_LINK_HOST.'/'.$code.'?tag[]=a&tag[]=b')->assertRedirect();

        // An array param has no scalar value: the placeholder falls back to ''.
        $this->assertSame('', Callback::query()->firstOrFail()->data['sub']);
    }

    public function test_broken_percent_encoding_is_scrubbed_not_fatal(): void
    {
        $code = $this->setupLink(['sub' => '{{click.query.sub_id}}']);

        // %E0%A4 decodes to an incomplete multibyte sequence — invalid UTF-8
        // that Postgres jsonb would reject; the value must be scrubbed, the
        // callback still created.
        $this->get(static::SHORT_LINK_HOST.'/'.$code.'?sub_id=%E0%A4')->assertRedirect();

        $callback = Callback::query()->firstOrFail();
        $this->assertIsString($callback->data['sub']);
    }

    public function test_deep_array_params_do_not_break_the_callback(): void
    {
        $code = $this->setupLink(['sub' => '{{click.query.a}}']);

        $this->get(static::SHORT_LINK_HOST.'/'.$code.'?a[b][c][d]=x')->assertRedirect();

        $this->assertSame('', Callback::query()->firstOrFail()->data['sub']);
    }

    public function test_dotted_param_names_stay_literal(): void
    {
        $code = $this->setupLink(['sub' => '{{click.query.sub.id}}']);

        $this->get(static::SHORT_LINK_HOST.'/'.$code.'?sub.id=abc')->assertRedirect();

        // parse_str would have mangled the stored key to `sub_id`, silently
        // posting '' forever; names must stay literal.
        $this->assertSame('abc', Callback::query()->firstOrFail()->data['sub']);
    }

    public function test_hyphenated_param_names_are_supported(): void
    {
        $code = $this->setupLink(['sub' => '{{click.query.sub-id}}']);

        $this->get(static::SHORT_LINK_HOST.'/'.$code.'?sub-id=abc')->assertRedirect();

        $this->assertSame('abc', Callback::query()->firstOrFail()->data['sub']);
    }

    public function test_query_placeholders_reach_the_callback_payload(): void
    {
        $code = $this->setupLink([
            'sub' => '{{click.query.sub_id}}',
            'missing' => '{{click.query.nope}}',
            'foreign' => '{{partner.syntax}}',
        ]);

        $this->get(static::SHORT_LINK_HOST.'/'.$code.'?sub_id=abc-123')->assertRedirect();

        $data = Callback::query()->firstOrFail()->data;

        $this->assertSame('abc-123', $data['sub']);
        // Absent query param → empty string (contract).
        $this->assertSame('', $data['missing']);
        // Unknown non-query placeholder stays verbatim.
        $this->assertSame('{{partner.syntax}}', $data['foreign']);
    }

    public function test_the_click_stores_the_visit_query_string(): void
    {
        $code = $this->setupLink(['x' => '{{click.id}}']);

        $this->get(static::SHORT_LINK_HOST.'/'.$code.'?sub_id=abc&utm_source=tg')->assertRedirect();

        $query = (string) Click::query()->firstOrFail()->visited_query;
        $this->assertStringContainsString('sub_id=abc', $query);
        $this->assertStringContainsString('utm_source=tg', $query);
    }

    private function setupLink(array $callbackData): string
    {
        Http::fake();

        $service = Service::query()->create([
            'name' => 'Query Service '.fake()->unique()->word(),
            'callback_url' => 'https://93.184.216.34/hook',
        ]);

        $link = Link::factory()->create([
            'service_id' => $service->id,
            'callback_data' => $callbackData,
        ]);

        $code = fake()->unique()->bothify('????####');
        $link->update(['code' => $code]);

        Rule::factory()->create([
            'link_id' => $link->id,
            'url_id' => Url::factory()->create()->id,
            'priority' => 1,
        ]);

        return $code;
    }
}
