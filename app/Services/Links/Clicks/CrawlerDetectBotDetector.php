<?php

namespace App\Services\Links\Clicks;

use Jaybizzle\CrawlerDetect\CrawlerDetect;

readonly class CrawlerDetectBotDetector implements BotDetector
{
    public function __construct(
        private CrawlerDetect $crawlerDetect
    ) {}

    public function isBot(?string $userAgent): bool
    {
        if ($userAgent === null || trim($userAgent) === '') {
            return false;
        }

        return $this->crawlerDetect->isCrawler($userAgent);
    }
}
