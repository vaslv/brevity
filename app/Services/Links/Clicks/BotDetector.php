<?php

namespace App\Services\Links\Clicks;

interface BotDetector
{
    public function isBot(?string $userAgent): bool;
}
