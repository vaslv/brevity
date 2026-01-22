<?php

namespace App\Http\Controllers;

use App\Models\Link;
use App\Services\Links\Conditions\ConditionContext;
use App\Services\Links\LinkUrlResolver;
use Illuminate\Http\Request;

class ResolveLink extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(string $code, Request $request, LinkUrlResolver $resolver)
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

        $url = $resolver->resolve($link, $context);

        if (! $url) {
            abort(404);
        }

        return redirect()->away($url->value);
    }
}
