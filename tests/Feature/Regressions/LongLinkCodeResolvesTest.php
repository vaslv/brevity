<?php

namespace Tests\Feature\Regressions;

use App\Models\Link;
use App\Models\Rule;
use App\Models\Service;
use App\Models\Url;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression for docs/AUDIT_2026-06.md — H7.
 *
 * Link.code is Hashids.encode(id) stored in what was varchar(8); the code grows
 * to 9 chars at id ~52.5B and up to 14 at the bigint ceiling, overflowing the
 * column. The column is now varchar(16) and the route constraint widened to
 * {5,16} in lock-step, so a code longer than 8 chars both stores and resolves.
 */
class LongLinkCodeResolvesTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_code_longer_than_eight_characters_stores_and_resolves(): void
    {
        $target = 'https://example.com/landing';

        $service = Service::query()->create(['name' => 'Svc '.fake()->unique()->word()]);
        $link = Link::query()->create(['service_id' => $service->id, 'forward_query' => false]);

        // 12 chars — would overflow varchar(8) on update and 404 under the old
        // {5,8} route constraint.
        $code = 'abcdEFGH1234';
        $link->update(['code' => $code]);

        $url = Url::query()->create(['value' => $target]);
        Rule::query()->create(['link_id' => $link->id, 'url_id' => $url->id, 'priority' => 1]);

        $this->get('/'.$code)->assertRedirect($target);
    }
}
