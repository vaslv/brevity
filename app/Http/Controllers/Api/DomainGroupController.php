<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DomainGroupResource;
use App\Models\DomainGroup;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DomainGroupController extends Controller
{
    /**
     * List every domain group with the number of domains it holds.
     */
    public function index(): AnonymousResourceCollection
    {
        $groups = DomainGroup::query()
            ->withCount('domains')
            ->orderBy('name')
            ->get();

        return DomainGroupResource::collection($groups);
    }
}
