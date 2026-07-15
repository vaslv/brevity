<?php

namespace Tests\Feature\Filament;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Vaslv\FilamentTopbarMenu\Models\TopbarMenuItem;

/**
 * The version chip and the TopbarMenuPlugin both render at TOPBAR_LOGO_AFTER,
 * where same-hook output follows registration order. MainPanelProvider must
 * register the chip before the plugin so the topbar reads logo → version → menu.
 */
class TopbarVersionChipOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_version_chip_renders_before_the_topbar_menu(): void
    {
        $this->actingAs(User::query()->create([
            'name' => 'Admin',
            'email' => 'admin'.fake()->unique()->randomNumber().'@example.test',
            'password' => 'password',
        ]));
        Filament::setCurrentPanel('main');

        // A visible root item so the plugin actually renders its menu markup.
        TopbarMenuItem::create([
            'label' => 'Docs',
            'url' => 'https://docs.example.test',
        ]);

        $html = (string) $this->get('/')->assertOk()->getContent();

        $chip = 'font-variant-numeric:tabular-nums';
        $menu = strpos($html, 'fi-topbar-nav-groups');
        $this->assertNotFalse($menu, 'topbar menu did not render');

        // The chip renders twice (sidebar + topbar). The topbar copy shares the
        // TOPBAR_LOGO_AFTER hook with the menu, so it is adjacent to it; the
        // sidebar copy lives in another layout region, far away. So the chip
        // nearest the menu is the topbar one, and the side it sits on proves the
        // order regardless of where sidebar vs topbar fall in the DOM.
        $chipBefore = strrpos(substr($html, 0, $menu), $chip);
        $chipAfter = strpos($html, $chip, $menu);
        $this->assertTrue($chipBefore !== false || $chipAfter !== false, 'version chip did not render');

        $distanceBefore = $chipBefore === false ? PHP_INT_MAX : $menu - $chipBefore;
        $distanceAfter = $chipAfter === false ? PHP_INT_MAX : $chipAfter - $menu;

        $this->assertLessThan(
            $distanceAfter,
            $distanceBefore,
            'the version chip must render immediately before the topbar menu',
        );
    }
}
