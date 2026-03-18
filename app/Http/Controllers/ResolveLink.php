<?php

namespace App\Http\Controllers;

use App\Models\Link;
use App\Services\Links\Conditions\ConditionContext;
use App\Services\Links\LinkRuleResolver;
use App\Services\Links\TransitionMode;
use Illuminate\Http\Request;

class ResolveLink extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(string $code, Request $request, LinkRuleResolver $resolver)
    {
        $link = Link::findByCode($code);

        if (! $link) {
            abort(404);
        }

        // The domain is set for the Link, but it does not match the current one.
        if ($link->domain && $link->domain->value !== $request->host()) {
            abort(404);
        }

        $context = new ConditionContext($link, $request, now()->toImmutable());

        $rule = $resolver->resolve($link, $context);

        if (! $rule) {
            abort(404);
        }

        $transitionMode = TransitionMode::tryFrom((string) $rule->transition_mode) ?? TransitionMode::Direct;

        if (! $transitionMode->usesPage()) {
            return redirect()->away($rule->url->value);
        }

        $countdownSeconds = 5;

        return response()->view('link.redirect', [
            'transitionMode' => $transitionMode,
            'targetUrl' => $rule->url->value,
            'countdownSeconds' => $countdownSeconds,
        ]);
    }
}
