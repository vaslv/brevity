<?php

namespace App\Services\Links\Clicks;

use DeviceDetector\Parser\Bot;

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

        // Only the verdict is needed, so use the standalone bot parser: a full
        // DeviceDetector::parse() would also run the OS/client/device regex
        // cascade for every NON-bot UA — an order of magnitude more work on
        // the detect-bots backfill and the unique-UA miss path.
        $parser = new Bot;
        $parser->setUserAgent($userAgent);
        $parser->discardDetails();

        return $parser->parse() !== null;
    }
}
