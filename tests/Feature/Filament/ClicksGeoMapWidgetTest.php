<?php

namespace Tests\Feature\Filament;

use App\Filament\Widgets\ClicksGeoMap;
use App\Models\Click;
use App\Models\GeoLocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The dashboard geo map plots located clicks as city bubbles: markers aggregate
 * clicks per dictionary tuple within the window, heaviest city first. Tuples
 * without coordinates (pre-backfill) and clicks outside the window stay off the
 * map rather than distorting it.
 */
class ClicksGeoMapWidgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_country_only_tuple_is_labeled_with_the_country_code(): void
    {
        $countryOnly = GeoLocation::factory()->create([
            'country_code' => 'JP',
            'region' => '',
            'city' => '',
        ]);

        Click::factory()->create(['geo_location_id' => $countryOnly->id]);

        $this->assertSame('JP', (new ClicksGeoMap)->getMarkers()[0]['label']);
    }

    public function test_markers_aggregate_clicks_per_city_heaviest_first(): void
    {
        $london = GeoLocation::factory()->create([
            'country_code' => 'GB',
            'region' => 'England',
            'city' => 'London',
            'latitude' => 51.5142,
            'longitude' => -0.0931,
        ]);
        $berlin = GeoLocation::factory()->create([
            'country_code' => 'DE',
            'region' => 'Berlin',
            'city' => 'Berlin',
            'latitude' => 52.52,
            'longitude' => 13.405,
        ]);

        Click::factory()->count(3)->create(['geo_location_id' => $london->id]);
        Click::factory()->create(['geo_location_id' => $berlin->id]);
        Click::factory()->create(['geo_location_id' => null]);

        $markers = (new ClicksGeoMap)->getMarkers();

        $this->assertSame(
            [
                ['label' => 'London, GB', 'count' => 3],
                ['label' => 'Berlin, DE', 'count' => 1],
            ],
            array_map(fn (array $marker): array => [
                'label' => $marker['label'],
                'count' => $marker['count'],
            ], $markers),
        );
        $this->assertEqualsWithDelta(51.5142, $markers[0]['lat'], 0.0001);
        $this->assertEqualsWithDelta(-0.0931, $markers[0]['lng'], 0.0001);
    }

    public function test_markers_exclude_tuples_without_coordinates_and_old_clicks(): void
    {
        $noCoordinates = GeoLocation::factory()->create([
            'latitude' => null,
            'longitude' => null,
        ]);
        $located = GeoLocation::factory()->create();

        Click::factory()->create(['geo_location_id' => $noCoordinates->id]);
        Click::factory()->create([
            'geo_location_id' => $located->id,
            'created_at' => now()->subDays(ClicksGeoMap::DAYS + 1),
        ]);

        $this->assertSame([], (new ClicksGeoMap)->getMarkers());
    }

    public function test_the_widget_renders_an_empty_state_without_located_clicks(): void
    {
        $this->withoutVite();

        Livewire::test(ClicksGeoMap::class)
            ->assertDontSee('data-clicks-geo-map', escape: false)
            ->assertSee(__('widgets.clicks_geo_map.empty'));
    }

    public function test_the_widget_renders_the_map_container_with_marker_data(): void
    {
        $this->withoutVite();

        $geo = GeoLocation::factory()->create(['city' => 'London', 'country_code' => 'GB']);
        Click::factory()->create(['geo_location_id' => $geo->id]);

        Livewire::test(ClicksGeoMap::class)
            ->assertSee('data-clicks-geo-map', escape: false)
            ->assertSee('London, GB');
    }
}
