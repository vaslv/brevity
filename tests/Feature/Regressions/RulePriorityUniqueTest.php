<?php

namespace Tests\Feature\Regressions;

use App\Filament\Resources\Links\Pages\EditLink;
use App\Filament\Resources\Links\RelationManagers\RulesRelationManager;
use App\Models\Link;
use App\Models\Rule;
use App\Models\Service;
use App\Models\Url;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Guard for docs/CODE_REVIEW.md — m5.
 *
 * `rules` has a unique (link_id, priority). The admin priority field now
 * validates per-link uniqueness so a duplicate shows a form error instead of a
 * raw DB 500.
 */
class RulePriorityUniqueTest extends TestCase
{
    use RefreshDatabase;

    public function test_distinct_priority_within_a_link_is_accepted(): void
    {
        [$link, $url] = $this->makeLinkWithRule();
        $this->actAsAdmin();

        Livewire::test(RulesRelationManager::class, [
            'ownerRecord' => $link,
            'pageClass' => EditLink::class,
        ])
            ->callTableAction('create', data: [
                'url_id' => $url->id,
                'priority' => 2,
            ])
            ->assertHasNoTableActionErrors();

        $this->assertSame(2, $link->rules()->count());
    }

    public function test_duplicate_priority_within_a_link_is_rejected(): void
    {
        [$link, $url] = $this->makeLinkWithRule();
        $this->actAsAdmin();

        Livewire::test(RulesRelationManager::class, [
            'ownerRecord' => $link,
            'pageClass' => EditLink::class,
        ])
            ->callTableAction('create', data: [
                'url_id' => $url->id,
                'priority' => 1, // already taken by the existing rule
            ])
            ->assertHasTableActionErrors(['priority']);
    }

    private function actAsAdmin(): void
    {
        $this->actingAs(User::query()->create([
            'name' => 'Admin',
            'email' => 'admin'.fake()->unique()->randomNumber().'@example.test',
            'password' => 'password',
        ]));
        Filament::setCurrentPanel('main');
    }

    /**
     * @return array{0: Link, 1: Url}
     */
    private function makeLinkWithRule(): array
    {
        $service = Service::query()->create(['name' => 'Rule Service '.fake()->unique()->word()]);
        $link = Link::query()->create(['service_id' => $service->id, 'forward_query' => false]);
        $url = Url::query()->create(['value' => 'https://target.example/'.fake()->unique()->slug()]);
        Rule::query()->create(['link_id' => $link->id, 'url_id' => $url->id, 'priority' => 1]);

        return [$link, $url];
    }
}
