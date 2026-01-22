<?php

namespace App\Http\Controllers;

use App\Models\Link;
use Illuminate\Http\Request;

class ResolveLink extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(string $code, Request $request)
    {
        $link = Link::findByCode($code);

        if (! $link) {
            abort(404);
        }

        // The domain is set for the Link, but it does not match the current one.
        if ($link->domain && $link->domain->value !== $request->host()) {
            abort(404);
        }

        dd(
            $link->rules
        );
    }
}
