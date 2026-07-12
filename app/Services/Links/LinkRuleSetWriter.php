<?php

namespace App\Services\Links;

use App\Models\Condition;
use App\Models\Link;
use App\Models\Url;
use App\Services\Links\Conditions\ConditionRegistry;
use League\Uri\Modifier;

/**
 * Writes a link's full ordered rule set (create and PATCH-replace share the
 * exact same dictionary resolution and priority numbering). The set is always
 * replaced atomically as a whole — rules have no identity across writes.
 */
readonly class LinkRuleSetWriter
{
    public function __construct(
        private ConditionRegistry $registry,
    ) {}

    /**
     * Rule data is already normalized to a `conditions` list (the single legacy
     * `condition` is folded into it upstream by the request).
     *
     * @param  array<int, array{
     *     url: string,
     *     conditions?: array<int, array{type: string, data?: array<string, mixed>}>,
     *     transition_mode?: ?string,
     * }>  $rules
     */
    public function replace(Link $link, array $rules): void
    {
        $link->rules()->delete();

        foreach ($rules as $index => $ruleData) {
            $rule = $link->rules()->create([
                'url_id' => $this->resolveUrlId($ruleData['url']),
                'transition_mode' => $ruleData['transition_mode'] ?? null,
                'priority' => $index + 1,
            ]);

            $conditionIds = array_map(
                fn (array $condition): int => $this->resolveConditionId($condition),
                $ruleData['conditions'] ?? [],
            );

            // array_unique: the same condition twice on one rule is a no-op for
            // AND semantics and would violate the pivot's unique index.
            if ($conditionIds !== []) {
                $rule->conditions()->attach(array_unique($conditionIds));
            }
        }
    }

    /**
     * @param  array{type: string, data?: array<string, mixed>}  $condition
     */
    private function resolveConditionId(array $condition): int
    {
        $type = $condition['type'];
        $data = $condition['data'] ?? [];

        // Persist only handler-known fields so stray keys don't fragment the
        // (type, data) dedup index with near-duplicate rows.
        if ($handler = $this->registry->getHandler($type)) {
            $data = $handler::validate($data);
        }

        $encoded = json_encode($data);

        Condition::insertOrIgnore([
            'type' => $type,
            'data' => $encoded,
            'created_at' => now(),
        ]);

        return Condition::query()
            ->where('type', $type)
            ->whereRaw('"data"::jsonb = ?::jsonb', [$encoded])
            ->value('id');
    }

    private function resolveUrlId(string $rawUrl): int
    {
        $normalized = Modifier::wrap($rawUrl)
            ->normalize()
            ->sortQuery()
            ->toString();

        return Url::firstOrCreate(['value' => $normalized])->id;
    }
}
