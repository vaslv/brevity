<?php

namespace App\Services\Links;

use App\Models\Condition;
use App\Models\Domain;
use App\Models\Link;
use App\Models\Url;
use App\Services\Links\Conditions\ConditionRegistry;
use App\Services\Links\Domains\DomainSelectionStrategy;
use App\Services\Links\Domains\DomainSelector;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use League\Uri\Modifier;

class LinkCreator
{
    public function __construct(
        private readonly ConditionRegistry $registry,
        private readonly DomainSelector $domainSelector,
    ) {}

    /**
     * @param  array{
     *     service_id: int,
     *     title?: ?string,
     *     forward_query?: ?bool,
     *     callback_data?: ?array<string, mixed>,
     *     domain?: ?string,
     *     domain_strategy?: ?string,
     *     domain_group?: ?string,
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
                'domain_id' => $this->resolveDomainId($data),
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

    /**
     * Resolve which domain a new link is created on, in precedence order:
     *   1. explicit `domain` value;
     *   2. `domain_strategy` (optionally scoped to a `domain_group` code);
     *   3. the default domain, if one is marked;
     *   4. none — the link resolves via config('app.url').
     *
     * @param  array{domain?: ?string, domain_strategy?: ?string, domain_group?: ?string}  $data
     */
    private function resolveDomainId(array $data): ?int
    {
        $value = $data['domain'] ?? null;

        if (is_string($value) && $value !== '') {
            return Domain::where('value', $value)->value('id');
        }

        $strategy = $data['domain_strategy'] ?? null;

        if (is_string($strategy) && $strategy !== '') {
            return $this->selectDomainId(
                DomainSelectionStrategy::from($strategy),
                $data['domain_group'] ?? null,
            );
        }

        return Domain::where('is_default', true)->value('id');
    }

    private function resolveUrlId(string $rawUrl): int
    {
        $normalized = Modifier::wrap($rawUrl)
            ->normalize()
            ->sortQuery()
            ->toString();

        return Url::firstOrCreate(['value' => $normalized])->id;
    }

    private function selectDomainId(DomainSelectionStrategy $strategy, ?string $groupCode): int
    {
        $domain = $this->domainSelector->select($strategy, $groupCode);

        if ($domain !== null) {
            return $domain->id;
        }

        // The client asked for a strategy but the scope has no domains; surface
        // it as a validation error rather than silently dropping the strategy.
        throw ValidationException::withMessages(
            $groupCode !== null
                ? ['domain_group' => 'The selected domain group has no domains to choose from.']
                : ['domain_strategy' => 'There are no domains available to choose from.']
        );
    }
}
