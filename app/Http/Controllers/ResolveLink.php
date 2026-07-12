<?php

namespace App\Http\Controllers;

use App\Jobs\RecordClickJob;
use App\Models\Link;
use App\Services\Links\Conditions\ConditionContext;
use App\Services\Links\LinkRuleResolver;
use App\Services\Links\RuleVariantSelector;
use App\Services\Links\TransitionMode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class ResolveLink extends Controller
{
    private const int REDIRECT_COUNTDOWN_SECONDS = 5;

    /**
     * @param  array<string, int|string>  $parts
     */
    private function buildUrl(array $parts): string
    {
        $scheme = isset($parts['scheme']) ? $parts['scheme'].'://' : '';
        $auth = '';

        if (isset($parts['user'])) {
            $auth = $parts['user'];
            if (isset($parts['pass'])) {
                $auth .= ':'.$parts['pass'];
            }
            $auth .= '@';
        }

        $host = $parts['host'] ?? '';
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';
        $path = $parts['path'] ?? '';
        $query = ! empty($parts['query']) ? '?'.$parts['query'] : '';
        $fragment = isset($parts['fragment']) ? '#'.$parts['fragment'] : '';

        return $scheme.$auth.$host.$port.$path.$query.$fragment;
    }

    /**
     * Merge incoming request query params into the target URL.
     * Target's existing params take precedence for duplicate keys.
     */
    private function forwardQueryParams(string $targetUrl, Request $request): string
    {
        $incoming = $request->query();

        // The tracking-disable parameter is service plumbing — never leak it
        // into the target URL.
        $disableParam = (string) config('tracking.disable_param');

        if ($disableParam !== '') {
            unset($incoming[$disableParam]);
        }

        if (! is_array($incoming) || $incoming === []) {
            return $targetUrl;
        }

        $parts = parse_url($targetUrl);

        if ($parts === false) {
            return $targetUrl;
        }

        $existing = [];

        if (! empty($parts['query'])) {
            parse_str($parts['query'], $existing);
        }

        $parts['query'] = http_build_query($existing + $incoming);

        return $this->buildUrl($parts);
    }

    private function hasAllowedScheme(string $url): bool
    {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true);
    }

    private function hasDomainMismatch(Link $link, Request $request): bool
    {
        if ($link->domain === null) {
            return false;
        }

        return strcasecmp($link->domain->value, $request->host()) !== 0;
    }

    private function trackingDisabled(Request $request): bool
    {
        $param = (string) config('tracking.disable_param');

        return $param !== '' && $request->query->has($param);
    }

    /**
     * Handle the incoming request.
     */
    public function __invoke(
        string $code,
        Request $request,
        LinkRuleResolver $resolver,
        RuleVariantSelector $variantSelector,
    ): RedirectResponse|Response {
        $link = Link::findByCode($code);

        if (! $link) {
            abort(404);
        }

        // The domain is set for the Link, but it does not match the current one.
        if ($this->hasDomainMismatch($link, $request)) {
            abort(404);
        }

        // A link outside its lifecycle window or over its click limit is
        // indistinguishable from a missing one: 404, no click, no callback.
        if (! $link->isAlive(now())) {
            abort(404);
        }

        $context = new ConditionContext($link, $request, now()->toImmutable());

        $rule = $resolver->resolve($link, $context);

        if (! $rule) {
            abort(404);
        }

        // A/B split (GAP-04): the winning rule may fan out to weighted target
        // URLs. Without variants this returns the rule's own url unchanged.
        $target = $variantSelector->select($rule, $link, $request);

        // Re-validate the resolved target scheme at read time. The API enforces
        // http/https on write, but seeders/raw SQL/admin edits could bypass that;
        // never emit a non-web scheme (e.g. javascript:) into a Location header or
        // a clickable href.
        if (! $this->hasAllowedScheme($target['url_value'])) {
            abort(404);
        }

        $transitionMode = TransitionMode::tryFrom((string) $rule->transition_mode) ?? TransitionMode::Direct;

        // Test visits opt out of tracking entirely: no click, no callback.
        if (! $this->trackingDisabled($request)) {
            RecordClickJob::dispatch(
                (string) Str::uuid(),
                $link->id,
                $target['url_id'],
                $request->ip(),
                $request->headers->get('referer'),
                $request->userAgent(),
                $request->getQueryString(),
                $target['variant']?->id,
            );
        }

        $targetUrl = $link->forward_query
            ? $this->forwardQueryParams($target['url_value'], $request)
            : $target['url_value'];

        // Every visit must reach the tracker: a cached redirect would swallow
        // repeat clicks — and every click is a callback to the partner.
        if (! $transitionMode->usesPage()) {
            return redirect()->away($targetUrl)->header('Cache-Control', 'no-store');
        }

        return response()->view('link.redirect', [
            'transitionMode' => $transitionMode,
            'targetUrl' => $targetUrl,
            'countdownSeconds' => self::REDIRECT_COUNTDOWN_SECONDS,
        ])->header('Cache-Control', 'no-store');
    }
}
