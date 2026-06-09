<?php

namespace Tests\Feature;

use App\Models\Condition;
use App\Models\Domain;
use App\Models\Link;
use App\Models\Rule;
use App\Models\Url;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * End-to-end coverage of the resolve controller's selection logic
 * (docs/AUDIT_2026-06.md — Phase 4): unknown code / domain mismatch / no-match
 * 404s, priority + condition gating, and forward_query merge semantics.
 * Transition-mode rendering is covered by ResolveLinkTransitionModeTest.
 */
class ResolveLinkResolutionTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_domain_mismatch_returns_404(): void
    {
        $domain = Domain::factory()->create(['value' => 'mismatch.example.com']);
        $link = Link::factory()->forDomain($domain)->create();
        Rule::factory()->for($link)->create();

        // The request arrives on the default test host (localhost), not the
        // link's domain, so the resolver hides it.
        $this->get('/'.$link->code)->assertNotFound();
    }

    public function test_a_failed_condition_falls_through_to_the_fallback_rule(): void
    {
        $this->travelTo(Carbon::parse('2026-01-01T00:00:00+00:00'));

        $link = Link::factory()->create();
        $sale = Url::factory()->create(['value' => 'https://example.com/sale']);
        $home = Url::factory()->create(['value' => 'https://example.com/home']);
        $past = Condition::factory()->timeBefore('2020-01-01T00:00:00+00:00')->create();

        Rule::factory()->for($link)->priority(1)->withCondition($past)
            ->state(['url_id' => $sale->id])->create();
        Rule::factory()->for($link)->priority(2)
            ->state(['url_id' => $home->id])->create();

        $this->get('/'.$link->code)->assertRedirect('https://example.com/home');
    }

    public function test_a_higher_priority_matching_rule_wins_and_records_its_url(): void
    {
        $this->travelTo(Carbon::parse('2026-01-01T00:00:00+00:00'));

        $link = Link::factory()->create();
        $sale = Url::factory()->create(['value' => 'https://example.com/sale']);
        $home = Url::factory()->create(['value' => 'https://example.com/home']);
        $future = Condition::factory()->timeBefore('2030-01-01T00:00:00+00:00')->create();

        Rule::factory()->for($link)->priority(1)->withCondition($future)
            ->state(['url_id' => $sale->id])->create();
        Rule::factory()->for($link)->priority(2)
            ->state(['url_id' => $home->id])->create();

        $this->get('/'.$link->code)->assertRedirect('https://example.com/sale');

        $this->assertDatabaseHas('clicks', [
            'link_id' => $link->id,
            'url_id' => $sale->id,
        ]);
    }

    public function test_a_soft_deleted_link_returns_404(): void
    {
        $link = Link::factory()->create();
        Rule::factory()->for($link)->create();
        $code = $link->code;

        // findByCode applies the SoftDeletes scope, so a trashed link is treated
        // as disabled (see docs/ARCHITECTURE.md — Модель данных).
        $link->delete();

        $this->get('/'.$code)->assertNotFound();
    }

    public function test_an_unknown_code_returns_404(): void
    {
        $this->get('/abcdef')->assertNotFound();
    }

    public function test_forward_query_merges_incoming_params_with_target_winning(): void
    {
        $link = Link::factory()->forwardingQuery()->create();
        $url = Url::factory()->create(['value' => 'https://example.com/p?a=1']);
        Rule::factory()->for($link)->state(['url_id' => $url->id])->create();

        $this->get('/'.$link->code.'?a=2&b=3')
            ->assertRedirect('https://example.com/p?a=1&b=3');
    }

    public function test_incoming_query_is_ignored_when_forward_query_is_off(): void
    {
        $link = Link::factory()->create();
        $url = Url::factory()->create(['value' => 'https://example.com/p?a=1']);
        Rule::factory()->for($link)->state(['url_id' => $url->id])->create();

        $this->get('/'.$link->code.'?b=3')
            ->assertRedirect('https://example.com/p?a=1');
    }

    public function test_no_matching_rule_returns_404(): void
    {
        $this->travelTo(Carbon::parse('2026-01-01T00:00:00+00:00'));

        $link = Link::factory()->create();
        $past = Condition::factory()->timeBefore('2020-01-01T00:00:00+00:00')->create();
        Rule::factory()->for($link)->withCondition($past)->create();

        $this->get('/'.$link->code)->assertNotFound();
    }
}
