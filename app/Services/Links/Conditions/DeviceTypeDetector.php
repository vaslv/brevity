<?php

namespace App\Services\Links\Conditions;

interface DeviceTypeDetector
{
    /**
     * The device-type slugs a user agent belongs to. One UA can match several
     * (an iPhone is both `ios` and `mobile`).
     *
     * @return list<string>
     */
    public function typesFor(?string $userAgent): array;
}
