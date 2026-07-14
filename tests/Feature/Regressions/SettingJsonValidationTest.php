<?php

namespace Tests\Feature\Regressions;

use App\Filament\Resources\Settings\Pages\EditSetting;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;
use Vaslv\LaravelSettings\Models\Setting;

/**
 * Regression for docs/07-plans.md — r40.
 *
 * A json-typed setting is read back through a JSON_THROW_ON_ERROR cast, so a
 * typo saved through the panel would detonate every later settings() read with
 * an uncaught JsonException. The json branch of SettingForm now validates the
 * value, rejecting malformed JSON at the form instead of persisting it.
 */
class SettingJsonValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_saving_malformed_json_for_a_json_setting_is_rejected(): void
    {
        Livewire::test(EditSetting::class, ['record' => $this->jsonSetting()->getRouteKey()])
            ->fillForm(['value' => '{not valid json'])
            ->call('save')
            ->assertHasFormErrors(['value' => 'json']);
    }

    public function test_saving_valid_json_for_a_json_setting_succeeds(): void
    {
        Livewire::test(EditSetting::class, ['record' => $this->jsonSetting()->getRouteKey()])
            ->fillForm(['value' => '{"greeting": "hi"}'])
            ->call('save')
            ->assertHasNoFormErrors();
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::query()->create([
            'name' => 'Admin',
            'email' => 'admin@example.test',
            'password' => 'password',
        ]));
        Filament::setCurrentPanel('main');
    }

    private function jsonSetting(): Setting
    {
        return Setting::query()->create([
            'key' => 'json_config',
            'type' => 'json',
            'value' => '{"a": 1}',
        ]);
    }
}
