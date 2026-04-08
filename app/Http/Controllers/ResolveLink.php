<?php

namespace App\Http\Controllers;

use App\Models\Link;
use App\Services\Links\Clicks\ClickRecorder;
use App\Services\Links\Conditions\ConditionContext;
use App\Services\Links\LinkRuleResolver;
use App\Services\Links\TransitionMode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Throwable;

class ResolveLink extends Controller
{
    private const int REDIRECT_COUNTDOWN_SECONDS = 5;

    private function hasDomainMismatch(Link $link, Request $request): bool
    {
        if ($link->domain === null) {
            return false;
        }

        return strcasecmp($link->domain->value, $request->host()) !== 0;
    }

    /**
     * Handle the incoming request.
     */
    public function __invoke(
        string $code,
        Request $request,
        LinkRuleResolver $resolver,
        ClickRecorder $clickRecorder
    ): RedirectResponse|Response {
        $link = Link::findByCode($code);

        if (! $link) {
            abort(404);
        }

        // The domain is set for the Link, but it does not match the current one.
        if ($this->hasDomainMismatch($link, $request)) {
            abort(404);
        }

        $context = new ConditionContext($link, $request, now()->toImmutable());

        $rule = $resolver->resolve($link, $context);

        if (! $rule) {
            abort(404);
        }

        $transitionMode = TransitionMode::tryFrom((string) $rule->transition_mode) ?? TransitionMode::Direct;

        try {
            $clickRecorder->record($request, $link, $rule->url_id);
        } catch (Throwable $e) {
            Log::warning('Failed to record link click.', [
                'exception' => $e,
                'link_id' => $link->id,
                'service_id' => $link->service_id,
                'url_id' => $rule->url_id,
                'code' => $link->code,
                'host' => $request->host(),
                'ip' => $request->ip(),
            ]);
        }

        if (! $transitionMode->usesPage()) {
            return redirect()->away($rule->url->value);
        }

        return response()->view('link.redirect', [
            'transitionMode' => $transitionMode,
            'targetUrl' => $rule->url->value,
            'countdownSeconds' => self::REDIRECT_COUNTDOWN_SECONDS,
        ]);
    }
}
