<?php

namespace Tests\Feature\Regressions;

use App\Jobs\RecordClickJob;
use App\Models\Click;
use App\Models\Link;
use App\Models\Url;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionProperty;
use Tests\TestCase;

/**
 * Regression for docs/08-decisions.md (review 2026-07-13) — r20 (P1).
 *
 * `visitedQuery` and `ruleVariantId` were added to RecordClickJob's constructor
 * after the job first shipped. A payload serialized by the older production code
 * carries only the original six properties; on unserialize the two later
 * properties stay uninitialized, and constructor defaults do NOT apply. Reading
 * such a typed property directly (`$this->visitedQuery`) throws
 * "must not be accessed before initialization" on every try — and the deploy
 * runbook (docs/06-deploy.md) expects a Horizon backlog during the migration
 * window, so the whole backlog would be poisoned: no click, no counter, no
 * callback.
 *
 * Fixed: handle() reads both fields via `?? null` (isset-safe on an
 * uninitialized typed property). This test reproduces a pre-deploy payload and
 * asserts the click is still recorded.
 */
class RecordClickJobPayloadCompatibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_pre_deploy_payload_without_the_later_fields_still_records(): void
    {
        $link = Link::factory()->create();
        $url = Url::factory()->create();

        // Reproduce a payload serialized by the pre-deploy code: build the job
        // without its constructor, set only the original six properties, and
        // leave visitedQuery/ruleVariantId uninitialized. serialize() omits the
        // uninitialized typed properties exactly as the old code's payload would,
        // and unserialize() restores that partial object.
        $reflection = new ReflectionClass(RecordClickJob::class);
        $legacy = $reflection->newInstanceWithoutConstructor();

        foreach ([
            'clickUuid' => (string) Str::uuid(),
            'linkId' => $link->id,
            'urlId' => $url->id,
            'ip' => '203.0.113.9',
            'referrer' => null,
            'userAgent' => 'UA',
        ] as $property => $value) {
            $reflection->getProperty($property)->setValue($legacy, $value);
        }

        $job = unserialize(serialize($legacy));

        // Guard the reproduction itself: the later fields must genuinely be
        // uninitialized, or the test would pass without exercising the bug.
        $this->assertFalse((new ReflectionProperty($job, 'visitedQuery'))->isInitialized($job));
        $this->assertFalse((new ReflectionProperty($job, 'ruleVariantId'))->isInitialized($job));

        app()->call([$job, 'handle']);

        $this->assertSame(1, Click::query()->count());
        $this->assertNull(Click::query()->firstOrFail()->visited_query);
    }
}
