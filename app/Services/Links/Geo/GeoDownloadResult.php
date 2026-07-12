<?php

namespace App\Services\Links\Geo;

readonly class GeoDownloadResult
{
    public function __construct(
        public GeoDownloadStatus $status,
        public string $message,
    ) {}

    public static function failed(string $message): self
    {
        return new self(GeoDownloadStatus::Failed, $message);
    }

    public static function notConfigured(): self
    {
        return new self(
            GeoDownloadStatus::NotConfigured,
            'GEOIP_LICENSE_KEY is not set; the geo database download is disabled.',
        );
    }

    public static function skipped(string $message): self
    {
        return new self(GeoDownloadStatus::Skipped, $message);
    }

    public function succeeded(): bool
    {
        return $this->status === GeoDownloadStatus::Success;
    }

    public static function success(string $message): self
    {
        return new self(GeoDownloadStatus::Success, $message);
    }
}
