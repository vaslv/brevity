<?php

namespace App\Services\Links\Clicks;

use DeviceDetector\DeviceDetector;

/**
 * Bot detection backed by matomo/device-detector. The same library powers the
 * `device` redirect condition (stage 3), so the project carries one UA-parsing
 * dependency instead of two.
 */
readonly class DeviceDetectorBotDetector implements BotDetector
{
    public function isBot(?string $userAgent): bool
    {
        if ($userAgent === null || trim($userAgent) === '') {
            return false;
        }

        $detector = new DeviceDetector($userAgent);
        // We only need the bot verdict here — skip the (more expensive) full
        // device/client parse.
        $detector->discardBotInformation(false);
        $detector->parse();

        return $detector->isBot();
    }
}
