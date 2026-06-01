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
 * Guard test (originally written to probe CODE_REVIEW M4 — now RETRACTED).
 *
 * The review suspected that `SettingForm`'s `TextInput::make('key')->unique()`
 * (without `ignoreRecord: true`) would break editing existing settings. This
 * test proves it does NOT: Filament v5 defaults
 * `shouldUniqueValidationIgnoreRecordByDefault = true`, so the current record is
 * ignored automatically on edit. M4 is therefore not a bug.
 *
 * Kept as a regression guard: editing a setting must keep its key and persist.
 */
class SettingEditUniqueKeyTest extends TestCase
{
    use RefreshDatabase;

    public function test_editing_a_setting_keeps_its_key_without_unique_error(): void
    {
        $user = User::query()->create([
            'name' => 'Admin',
            'email' => 'admin@example.test',
            'password' => 'password',
        ]);

        $setting = Setting::query()->create([
            'key' => 'site_name',
            'type' => 'string',
            'value' => 'Old value',
        ]);

        $this->actingAs($user);
        Filament::setCurrentPanel('main');

        Livewire::test(EditSetting::class, ['record' => $setting->getRouteKey()])
            ->fillForm(['value' => 'New value'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('New value', $setting->refresh()->value);
    }
}
