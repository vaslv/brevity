<?php

namespace Tests\Feature;

use App\Models\Click;
use App\Models\Condition;
use App\Models\Domain;
use App\Models\DomainGroup;
use App\Models\IpAddress;
use App\Models\Link;
use App\Models\Referrer;
use App\Models\Rule;
use App\Models\Service;
use App\Models\Url;
use App\Models\UserAgent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Smoke coverage for the model factories introduced for the resolving-core
 * tests (docs/AUDIT_2026-06.md — Phase 4: factories + core coverage).
 */
class ModelFactoriesTest extends TestCase
{
    use RefreshDatabase;

    public function test_core_factories_create_persisted_rows(): void
    {
        $this->assertModelExists(Service::factory()->create());
        $this->assertModelExists(Domain::factory()->create());
        $this->assertModelExists(DomainGroup::factory()->create());
        $this->assertModelExists(Url::factory()->create());
        $this->assertModelExists(Condition::factory()->create());
        $this->assertModelExists(Link::factory()->create());
        $this->assertModelExists(Rule::factory()->create());
        $this->assertModelExists(Click::factory()->create());
        $this->assertModelExists(IpAddress::factory()->create());
        $this->assertModelExists(Referrer::factory()->create());
        $this->assertModelExists(UserAgent::factory()->create());
    }

    public function test_domain_as_default_marks_the_domain_default(): void
    {
        $domain = Domain::factory()->asDefault()->create();

        $this->assertTrue($domain->fresh()->is_default);
        $this->assertSame($domain->id, Domain::default()?->id);
    }

    public function test_link_factory_generates_a_route_valid_code(): void
    {
        $link = Link::factory()->create();

        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{5,16}$/', (string) $link->code);
    }

    public function test_rule_with_condition_attaches_a_time_before_condition(): void
    {
        $rule = Rule::factory()->withCondition()->create();

        $this->assertNotNull($rule->condition_id);
        $this->assertSame('time_before', $rule->condition->type);
    }
}
