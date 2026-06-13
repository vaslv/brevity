<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DomainResource;
use App\Models\Domain;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DomainController extends Controller
{
    /**
     * List domains, optionally scoped to a single domain group.
     *
     * Without `group_id` every domain is returned; with it, only the domains
     * attached to that group.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'group_id' => ['nullable', 'integer', 'exists:domain_groups,id'],
        ]);

        $groupId = $validated['group_id'] ?? null;

        $domains = Domain::query()
            ->when($groupId !== null, fn (Builder $query) => $query->whereHas(
                'domainGroups',
                fn (Builder $groups) => $groups->whereKey($groupId),
            ))
            ->orderBy('value')
            ->get();

        return DomainResource::collection($domains);
    }
}
