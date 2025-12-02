<?php

namespace App\Services\Links\Conditions;

use App\Models\Link;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

readonly class ConditionContext
{
    public function __construct(
        public Link $link,
        public Request $request,
        public CarbonImmutable $now,
    ) {}
}
