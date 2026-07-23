<?php

namespace Tests\Feature\Filament;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The panel declares the Brevity site icons: an SVG for modern browsers, the
 * ICO as a fallback, and a PNG for the iOS home screen.
 */
class SiteIconsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_icon_files_are_publicly_served(): void
    {
        $this->assertFileExists(public_path('images/favicon.svg'));
        $this->assertFileExists(public_path('favicon.ico'));
        $this->assertFileExists(public_path('apple-touch-icon.png'));
    }

    public function test_the_panel_declares_every_icon(): void
    {
        $this->actingAs(User::query()->create([
            'name' => 'Admin',
            'email' => 'admin'.fake()->unique()->randomNumber().'@example.test',
            'password' => 'password',
        ]));
        Filament::setCurrentPanel('main');

        $html = (string) $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('rel="icon" href="'.asset('images/favicon.svg').'"', $html);
        $this->assertStringContainsString('rel="alternate icon" href="'.asset('favicon.ico').'"', $html);
        $this->assertStringContainsString('rel="apple-touch-icon" href="'.asset('apple-touch-icon.png').'"', $html);
    }
}
