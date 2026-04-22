<?php

namespace App\Services\Links;

use App\Models\Condition;
use App\Models\Domain;
use App\Models\Link;
use App\Models\Url;
use Illuminate\Support\Facades\DB;
use League\Uri\Modifier;

class LinkCreator
{
    /**
     * @param  array{
     *     service_id: int,
     *     title?: ?string,
     *     forward_query?: ?bool,
     *     callback_data?: ?array<string, mixed>,
     *     domain?: ?string,
     *     rules: array<int, array{
     *         url: string,
     *         condition?: ?array{type: string, data?: array<string, mixed>},
     *         transition_mode?: ?string,
     *     }>,
     *  }  $data
     */
    public function create(array $data): Link
    {
        return DB::transaction(function () use ($data) {
            $link = Link::create([
                'service_id' => $data['service_id'],
                'domain_id' => $this->resolveDomainId($data['domain'] ?? null),
                'title' => $data['title'] ?? null,
                'forward_query' => $data['forward_query'] ?? false,
                'callback_data' => $data['callback_data'] ?? null,
            ]);

            foreach ($data['rules'] as $index => $ruleData) {
                $link->rules()->create([
                    'url_id' => $this->resolveUrlId($ruleData['url']),
                    'condition_id' => $this->resolveConditionId($ruleData['condition'] ?? null),
                    'transition_mode' => $ruleData['transition_mode'] ?? null,
                    'priority' => $index + 1,
                ]);
            }

            return $link->load('rules.condition', 'rules.url');
        });
    }

    /**
     * @param  array{type: string, data?: array<string, mixed>}|null  $condition
     */
    private function resolveConditionId(?array $condition): ?int
    {
        if (empty($condition)) {
            return null;
        }

        $type = $condition['type'];
        $data = $condition['data'] ?? [];
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

    private function resolveDomainId(?string $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Domain::where('value', $value)->value('id');
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
