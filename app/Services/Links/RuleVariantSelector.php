<?php

namespace App\Services\Links;

use App\Models\Link;
use App\Models\Rule;
use App\Models\RuleVariant;
use Illuminate\Http\Request;

/**
 * Picks a winning rule's target when it runs an A/B split (GAP-04).
 *
 * The choice is sticky per visitor without any stored state: a deterministic
 * hash of (ip, user agent, link id) maps into the cumulative weight range, so
 * the same visitor always lands on the same variant while traffic overall
 * splits by weight. Falls back to the rule's own url when it has no variants.
 */
readonly class RuleVariantSelector
{
    /**
     * @return array{url_id: int, url_value: string, variant: RuleVariant|null}
     */
    public function select(Rule $rule, Link $link, Request $request): array
    {
        $variants = $rule->variants;

        if ($variants->isEmpty()) {
            return ['url_id' => $rule->url_id, 'url_value' => $rule->url->value, 'variant' => null];
        }

        $totalWeight = (int) $variants->sum('weight');
        $bucket = $this->stickyBucket($link, $request, $totalWeight);

        $cumulative = 0;
        foreach ($variants as $variant) {
            $cumulative += $variant->weight;
            if ($bucket < $cumulative) {
                return ['url_id' => $variant->url_id, 'url_value' => $variant->url->value, 'variant' => $variant];
            }
        }

        // Unreachable while weights sum correctly; keep the last variant as a
        // total-safe fallback rather than returning nothing.
        $last = $variants->last();

        return ['url_id' => $last->url_id, 'url_value' => $last->url->value, 'variant' => $last];
    }

    private function stickyBucket(Link $link, Request $request, int $totalWeight): int
    {
        $key = ($request->ip() ?? '').'|'.($request->userAgent() ?? '').'|'.$link->id;

        return crc32($key) % $totalWeight;
    }
}
