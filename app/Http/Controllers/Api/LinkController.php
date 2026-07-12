<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLinkRequest;
use App\Http\Resources\LinkResource;
use App\Http\Resources\LinkWithStatsResource;
use App\Models\Link;
use App\Services\Links\LinkCreator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class LinkController extends Controller
{
    public function show(string $code, Request $request): LinkWithStatsResource
    {
        // Tenant scope belongs in the query itself: a foreign, unknown or
        // soft-deleted code takes the exact same path to the same 404 — no
        // query-count or timing difference reveals other tenants' codes.
        $link = Link::query()
            ->where('code', $code)
            ->where('service_id', $request->user()->id)
            ->with(['rules.condition', 'rules.url', 'clickCounters', 'domain'])
            ->first();

        if ($link === null) {
            abort(404);
        }

        return LinkWithStatsResource::make($link);
    }

    /**
     * @throws Throwable
     */
    public function store(StoreLinkRequest $request, LinkCreator $linkCreator): JsonResponse
    {
        $validated = $request->safe()->only([
            'title',
            'forward_query',
            'callback_data',
            'valid_since',
            'valid_until',
            'max_clicks',
            'domain',
            'domain_strategy',
            'domain_group',
            'rules',
        ]);

        $validated['service_id'] = $request->user()->id;

        $link = $linkCreator->create($validated);

        return LinkResource::make($link)
            ->response()
            ->setStatusCode(201);
    }
}
