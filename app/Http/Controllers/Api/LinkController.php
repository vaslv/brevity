<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLinkRequest;
use App\Http\Requests\UpdateLinkRequest;
use App\Http\Resources\LinkResource;
use App\Http\Resources\LinkWithStatsResource;
use App\Models\Link;
use App\Services\Links\LinkCreator;
use App\Services\Links\LinkUpdater;
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
            ->with(['rules.conditions', 'rules.url', 'clickCounters', 'domain'])
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

    public function update(UpdateLinkRequest $request, string $code, LinkUpdater $linkUpdater): LinkResource
    {
        // Same tenant-scoped lookup as show(): foreign, unknown and
        // soft-deleted codes all take the identical path to 404.
        $link = Link::query()
            ->where('code', $code)
            ->where('service_id', $request->user()->id)
            ->first();

        if ($link === null) {
            abort(404);
        }

        // validated() of a `sometimes` request carries only the keys the
        // client sent — exactly the sentinel semantics LinkUpdater expects.
        return LinkResource::make($linkUpdater->update($link, $request->validated()));
    }
}
