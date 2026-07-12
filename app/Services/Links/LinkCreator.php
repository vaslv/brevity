<?php

namespace App\Services\Links;

use App\Models\Domain;
use App\Models\Link;
use App\Services\Links\Domains\DomainSelectionStrategy;
use App\Services\Links\Domains\DomainSelector;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LinkCreator
{
    public function __construct(
        private readonly DomainSelector $domainSelector,
        private readonly LinkRuleSetWriter $ruleSetWriter,
    ) {}

    /**
     * @param  array{
     *     service_id: int,
     *     title?: ?string,
     *     forward_query?: ?bool,
     *     callback_data?: ?array<string, mixed>,
     *     valid_since?: ?string,
     *     valid_until?: ?string,
     *     max_clicks?: ?int,
     *     domain?: ?string,
     *     domain_strategy?: ?string,
     *     domain_group?: ?string,
     *     rules: array<int, array{
     *         url: string,
     *         conditions?: array<int, array{type: string, data?: array<string, mixed>}>,
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
                'valid_since' => $this->parseInstant($data['valid_since'] ?? null),
                'valid_until' => $this->parseInstant($data['valid_until'] ?? null),
                'max_clicks' => $data['max_clicks'] ?? null,
            ]);

            $this->ruleSetWriter->replace($link, $data['rules']);

            return $link->load('rules.conditions', 'rules.url');
        });
    }

    /**
     * Eloquent's datetime cast formats an instance in its OWN timezone while
     * the timestamptz column is written in the session (UTC) zone — a non-UTC
     * offset would be silently relabeled, shifting the instant. Normalize to
     * UTC explicitly at the API boundary.
     */
    private function parseInstant(?string $value): ?CarbonImmutable
    {
        return $value === null ? null : CarbonImmutable::parse($value)->utc();
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
