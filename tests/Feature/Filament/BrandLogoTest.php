<?php

namespace Tests\Feature\Filament;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The panel renders the Brevity wordmark instead of Filament's default
 * text-based logo.
 */
class BrandLogoTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_logo_file_is_publicly_served(): void
    {
        $this->assertFileExists(public_path('images/logo.svg'));
    }

    public function test_the_panel_renders_the_brand_logo(): void
    {
        $this->actingAs(User::query()->create([
            'name' => 'Admin',
            'email' => 'admin'.fake()->unique()->randomNumber().'@example.test',
            'password' => 'password',
        ]));
        Filament::setCurrentPanel('main');

        $html = (string) $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString(asset('images/logo.svg'), $html);
    }
}
