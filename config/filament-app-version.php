<?php

declare(strict_types=1);

use Vaslv\FilamentAppVersion\Resolvers\ConfigVersionResolver;
use Vaslv\FilamentAppVersion\Resolvers\GitVersionResolver;

return [

    /*
    |--------------------------------------------------------------------------
    | Fallback value
    |--------------------------------------------------------------------------
    |
    | What the chip shows when no source produced a version: an image built by
    | hand without the APP_VERSION build argument, or a checkout with no .git.
    | Matches the Dockerfile's ARG default, so a hand-built image and a bare
    | checkout report the same thing.
    |
    */

    'fallback' => 'dev',

    /*
    |--------------------------------------------------------------------------
    | Resolver chain
    |--------------------------------------------------------------------------
    |
    | Version sources in descending order of priority; the first non-empty
    | value wins.
    |
    | The package ships `filament-app-version.version` → `app.version` →
    | composer.json. Both changes here are deliberate:
    |
    | - the package's own `version` key is dropped, so APP_VERSION enters the
    |   application through exactly one place, config/app.php;
    | - composer.json is dropped, because it no longer carries a `version`
    |   field — the git tag is the source of truth and `composer release`
    |   creates a tag only, without a release commit.
    |
    | GitVersionResolver takes over wherever APP_VERSION is absent: in local
    | development it returns the short commit SHA, read straight out of .git
    | with no shell_exec. A production image has no .git (see .dockerignore),
    | so there it costs one file_exists and returns null.
    |
    */

    'resolvers' => [
        [ConfigVersionResolver::class, 'app.version'],
        [GitVersionResolver::class],
    ],

];
