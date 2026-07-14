<?php

namespace Tests\Feature\Regressions;

use Filament\Facades\Filament;
use Filament\Http\Middleware\AuthenticateSession;
use Tests\TestCase;

/**
 * Regression for docs/07-plans.md — r41.
 *
 * The panel ran the bare `web` group, which does not include Filament's
 * AuthenticateSession. Without it, rotating a (possibly compromised) account's
 * password does not invalidate that account's other live sessions. The panel
 * must register AuthenticateSession explicitly, as stock Filament does.
 */
class PanelSessionAuthTest extends TestCase
{
    public function test_panel_stack_includes_authenticate_session(): void
    {
        $middleware = Filament::getPanel('main')->getMiddleware();

        $this->assertContains(AuthenticateSession::class, $middleware);
    }
}
