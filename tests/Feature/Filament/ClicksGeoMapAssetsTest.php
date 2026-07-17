<?php

namespace Tests\Feature\Filament;

use Illuminate\Foundation\Vite;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Regression for the 2026-07-17 production incident: the dashboard render hook
 * called @vite unconditionally, so an image built without frontend assets
 * turned every dashboard load into a fatal ViteManifestNotFoundException. A
 * missing manifest must cost the dashboard its map, never the whole page.
 *
 * The suite-wide Vite fake (TestCase::setUp) is undone here on purpose: the
 * guard's behavior only matters against the real Vite implementation.
 */
class ClicksGeoMapAssetsTest extends TestCase
{
    private string $publicPath;

    public function test_the_hook_renders_nothing_when_no_manifest_is_built(): void
    {
        $html = view('filament.clicks-geo-map-assets')->render();

        $this->assertSame('', trim($html));
    }

    public function test_the_hook_renders_the_bundle_when_the_manifest_exists(): void
    {
        File::makeDirectory($this->publicPath.'/build', recursive: true);
        File::put($this->publicPath.'/build/manifest.json', json_encode([
            'resources/js/widgets/clicks-geo-map.js' => [
                'file' => 'assets/clicks-geo-map-test.js',
                'src' => 'resources/js/widgets/clicks-geo-map.js',
                'isEntry' => true,
                'css' => ['assets/clicks-geo-map-test.css'],
            ],
        ]));

        $html = view('filament.clicks-geo-map-assets')->render();

        $this->assertStringContainsString('assets/clicks-geo-map-test.js', $html);
        $this->assertStringContainsString('assets/clicks-geo-map-test.css', $html);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->swap(Vite::class, new Vite);

        $this->publicPath = sys_get_temp_dir().'/brevity-public-'.uniqid();
        $this->app->usePublicPath($this->publicPath);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->publicPath);

        parent::tearDown();
    }
}
