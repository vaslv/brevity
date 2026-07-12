<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLinkRequest;
use App\Http\Resources\LinkResource;
use App\Services\Links\LinkCreator;
use Illuminate\Http\JsonResponse;
use Throwable;

class LinkController extends Controller
{
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
