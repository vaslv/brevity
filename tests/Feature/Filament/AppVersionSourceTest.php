<?php

namespace Tests\Feature\Filament;

use Tests\TestCase;
use Vaslv\FilamentAppVersion\Facades\AppVersion;

/**
 * The version comes from the git tag, carried into the image as APP_VERSION.
 *
 * composer.json no longer holds a `version` field, so the chain published in
 * config/filament-app-version.php has to cover both ends: the built image,
 * where config('app.version') is set from the build argument, and a working
 * copy, where nothing is set and the commit SHA from .git is the answer.
 */
class AppVersionSourceTest extends TestCase
{
    /**
     * A regression guard for the move away from composer.json: while the field
     * was there, `composer release` had to create a release commit on every
     * tag, and the field could silently drift from the tag it claimed to
     * mirror. Its absence is what makes the release tag-only.
     */
    public function test_composer_json_carries_no_version_field(): void
    {
        $composer = json_decode((string) file_get_contents(base_path('composer.json')), true);

        $this->assertIsArray($composer);
        $this->assertArrayNotHasKey('version', $composer);
    }

    public function test_it_falls_back_to_the_commit_sha_without_app_version(): void
    {
        config(['app.version' => null]);

        $this->assertMatchesRegularExpression('/^[0-9a-f]{7}$/', (string) AppVersion::get());
    }

    public function test_it_reports_the_version_baked_into_the_image(): void
    {
        config(['app.version' => '1.10.13']);

        $this->assertSame('1.10.13', AppVersion::get());
    }
}
