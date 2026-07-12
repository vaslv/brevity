<?php

namespace App\Services\Links\Conditions;

use DeviceDetector\DeviceDetector;

/**
 * Device-type extraction backed by matomo/device-detector (the same library
 * used for bot detection). Maps a user agent to the set of type slugs it
 * belongs to — OS family plus a coarse mobile/desktop class.
 *
 * No per-UA cache by design: a parse is sub-millisecond and only runs for
 * device-gated links; caching in this Octane singleton would accumulate
 * unbounded state across requests for no meaningful gain.
 */
readonly class DeviceDetectorDeviceTypeDetector implements DeviceTypeDetector
{
    /**
     * OS FAMILY (not name) → our slug. Family groups every Linux distro
     * (Ubuntu, Debian, …) under 'GNU/Linux', so we don't chase per-distro
     * names.
     */
    private const OS_SLUGS = [
        'iOS' => 'ios',
        'Android' => 'android',
        'Windows' => 'windows',
        'Mac' => 'macos',
        'GNU/Linux' => 'linux',
        'Chrome OS' => 'chromeos',
    ];

    public function typesFor(?string $userAgent): array
    {
        if ($userAgent === null || trim($userAgent) === '') {
            return [];
        }

        $detector = new DeviceDetector($userAgent);
        $detector->parse();

        if ($detector->isBot()) {
            return [];
        }

        $types = [];

        $osFamily = $detector->getOs('family');
        if (is_string($osFamily) && isset(self::OS_SLUGS[$osFamily])) {
            $types[] = self::OS_SLUGS[$osFamily];
        }

        // Coarse class: mobile covers smartphone/tablet/phablet/wearable, so an
        // iPhone yields both `ios` and `mobile`.
        if ($detector->isMobile()) {
            $types[] = 'mobile';
        } elseif ($detector->isDesktop()) {
            $types[] = 'desktop';
        }

        return array_values(array_unique($types));
    }
}
