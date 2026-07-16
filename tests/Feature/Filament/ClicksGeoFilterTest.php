<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Clicks\Pages\ListClicks;
use App\Filament\Resources\Clicks\Pages\ViewClick;
use App\Models\Click;
use App\Models\GeoLocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Geo columns and the country filter on the Clicks list: country/region/city
 * come from the geoLocation dictionary; the filter matches through the
 * relation, so clicks without geo data never match a selected country.
 */
class ClicksGeoFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_clicks_table_renders_the_geo_columns(): void
    {
        Click::factory()->create([
            'geo_location_id' => GeoLocation::factory()->create()->id,
        ]);

        Livewire::test(ListClicks::class)
            ->assertCanRenderTableColumn('geoLocation.country_code')
            ->assertTableColumnExists('geoLocation.region')
            ->assertTableColumnExists('geoLocation.city');
    }

    public function test_country_filter_scopes_clicks_to_the_selected_country(): void
    {
        $usClick = Click::factory()->create([
            'geo_location_id' => GeoLocation::factory()->create(['country_code' => 'US'])->id,
        ]);
        $deClick = Click::factory()->create([
            'geo_location_id' => GeoLocation::factory()->create(['country_code' => 'DE'])->id,
        ]);
        $noGeoClick = Click::factory()->create(['geo_location_id' => null]);

        Livewire::test(ListClicks::class)
            ->filterTable('country', 'US')
            ->assertCanSeeTableRecords([$usClick])
            ->assertCanNotSeeTableRecords([$deClick, $noGeoClick]);
    }

    public function test_view_click_shows_the_geo_entries(): void
    {
        $click = Click::factory()->create([
            'geo_location_id' => GeoLocation::factory()->create([
                'country_code' => 'US',
                'region' => 'California',
                'city' => 'San Francisco',
            ])->id,
        ]);

        Livewire::test(ViewClick::class, ['record' => $click->id])
            ->assertSee('US')
            ->assertSee('California')
            ->assertSee('San Francisco');
    }
}
