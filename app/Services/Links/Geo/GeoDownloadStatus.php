<?php

namespace App\Services\Links\Geo;

enum GeoDownloadStatus
{
    case Failed;
    case NotConfigured; // no MaxMind license key
    case Skipped;       // another download holds the lock
    case Success;
}
