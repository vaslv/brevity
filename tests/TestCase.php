<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * A short-link host from APP_HOST (phpunit.xml) that is NOT the technical
     * host (localhost). Short codes resolve here; the admin panel, API and
     * Horizon 404. Prefix resolver requests with it so they hit a short-link
     * domain instead of the technical host.
     */
    protected const string SHORT_LINK_HOST = 'http://lnk.test';
}
