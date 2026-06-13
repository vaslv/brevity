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
     * Without `group` every domain is returned; with it (a group code), only
     * the domains attached to that group.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'group' => ['nullable', 'string', 'exists:domain_groups,code'],
        ]);

        $groupCode = $validated['group'] ?? null;

        $domains = Domain::query()
            ->when($groupCode !== null, fn (Builder $query) => $query->whereHas(
                'domainGroups',
                fn (Builder $groups) => $groups->where('code', $groupCode),
            ))
            ->orderBy('value')
            ->get();

        return DomainResource::collection($domains);
    }
}
