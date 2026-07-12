<?php

return [
    /*
     * Path to the MaxMind GeoLite2-City database used to resolve click IPs to a
     * country/region/city (stage 4). Kept outside the repo — geo:download-db
     * fetches it and the traffic-triggered auto-update refreshes it. Geo
     * resolution is skipped (clicks stay unlocated) when the file is absent, so
     * a missing database never breaks click recording. `?:` guards the empty
     * env value so a blank GEOIP_DATABASE_PATH falls back to the default.
     */
    'database_path' => env('GEOIP_DATABASE_PATH') ?: storage_path('app/geoip/GeoLite2-City.mmdb'),

    /*
     * MaxMind license key for downloading and updating the database. Empty
     * disables both geo:download-db and the auto-update; lookups still work
     * against a database placed on disk manually.
     */
    'license_key' => env('GEOIP_LICENSE_KEY', ''),

    /*
     * The GeoLite2 edition to download. Also the basename of the .mmdb inside
     * the MaxMind tarball.
     */
    'edition' => 'GeoLite2-City',

    /*
     * Refresh the database once it is older than this many days. Checked after
     * a click (no cron); the download runs asynchronously and never blocks the
     * redirect or the click record.
     */
    'max_age_days' => (int) env('GEOIP_MAX_AGE_DAYS', 30),

    /*
     * After a run of failed downloads, wait this many minutes before trying
     * again — traffic keeps arriving, so back off instead of hammering MaxMind.
     */
    'download_backoff_minutes' => 360,

    /*
     * MaxMind permalink endpoint; the edition and license key are appended as
     * query parameters at download time.
     */
    'download_url' => 'https://download.maxmind.com/app/geoip_download',
];
