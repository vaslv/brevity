<?php

namespace App\Services\Links\Conditions;

use DeviceDetector\DeviceDetector;

/**
 * Device-type extraction backed by matomo/device-detector (the same library
 * used for bot detection). Maps a user agent to the set of type slugs it
 * belongs to — OS family plus a coarse mobile/desktop class.
 *
 * A single-entry memo instead of a per-UA cache: one resolve may evaluate
 * several device conditions against the same UA (a rule per platform is the
 * typical setup), so remembering the last parse removes the repeat work while
 * keeping O(1) state in this Octane singleton — an unbounded map would
 * accumulate across requests.
 */
class DeviceDetectorDeviceTypeDetector implements DeviceTypeDetector
{
    /**
     * Same cap as ClickRecorder: the header is attacker-controlled and this
     * parse runs on the redirect hot path — never feed the regex suite more
     * than a real UA's worth of bytes.
     */
    private const MAX_USER_AGENT_BYTES = 2000;

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

    /**
     * @var array<int, string>
     */
    private array $memoTypes = [];

    private ?string $memoUserAgent = null;

    public function typesFor(?string $userAgent): array
    {
        if ($userAgent === null || trim($userAgent) === '') {
            return [];
        }

        $userAgent = mb_strcut($userAgent, 0, self::MAX_USER_AGENT_BYTES, 'UTF-8');

        if ($userAgent === $this->memoUserAgent) {
            return $this->memoTypes;
        }

        $detector = new DeviceDetector($userAgent);
        $detector->parse();

        $types = [];

        if (! $detector->isBot()) {
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
        }

        $this->memoUserAgent = $userAgent;
        $this->memoTypes = array_values(array_unique($types));

        return $this->memoTypes;
    }
}
