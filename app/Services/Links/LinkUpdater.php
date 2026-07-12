<?php

namespace App\Services\Links;

use App\Models\Link;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Partial update with sentinel semantics (docs/03-api.md §5.2): a key absent
 * from the payload is untouched, an explicit null clears the value. `$input`
 * must therefore contain only the keys the client actually sent (a
 * FormRequest's validated() with `sometimes` rules provides exactly that).
 * Immutable by design: code, domain, service.
 */
readonly class LinkUpdater
{
    public function __construct(
        private LinkRuleSetWriter $ruleSetWriter,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function update(Link $link, array $input): Link
    {
        return DB::transaction(function () use ($link, $input): Link {
            // Serialize concurrent PATCHes of one link: two parallel rule-set
            // replacements would race on the unique (link_id, priority) index.
            Link::query()->whereKey($link->getKey())->lockForUpdate()->value('id');

            $attributes = [];

            foreach (['title', 'callback_data', 'max_clicks'] as $field) {
                if (array_key_exists($field, $input)) {
                    $attributes[$field] = $input[$field];
                }
            }

            // The column is NOT NULL: "clearing" a boolean means its default,
            // mirroring the creator's `?? false`.
            if (array_key_exists('forward_query', $input)) {
                $attributes['forward_query'] = $input['forward_query'] ?? false;
            }

            foreach (['valid_since', 'valid_until'] as $field) {
                if (array_key_exists($field, $input)) {
                    $attributes[$field] = $this->parseInstant($input[$field]);
                }
            }

            $this->assertWindowIsConsistent($link, $attributes);

            if ($attributes !== []) {
                $link->update($attributes);
            }

            if (array_key_exists('rules', $input)) {
                $this->ruleSetWriter->replace($link, $input['rules']);
            }

            return $link->load('rules.condition', 'rules.url');
        });
    }

    /**
     * The request-level after_or_equal rule only sees fields present in the
     * payload; the merged state (new values over stored ones) must stay
     * consistent too — e.g. patching valid_until below the stored valid_since.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function assertWindowIsConsistent(Link $link, array $attributes): void
    {
        $since = array_key_exists('valid_since', $attributes) ? $attributes['valid_since'] : $link->valid_since;
        $until = array_key_exists('valid_until', $attributes) ? $attributes['valid_until'] : $link->valid_until;

        if ($since !== null && $until !== null && $since->greaterThan($until)) {
            throw ValidationException::withMessages([
                'valid_until' => 'The resulting valid_until would be before valid_since.',
            ]);
        }
    }

    /**
     * Eloquent's datetime cast formats an instance in its OWN timezone while
     * the timestamptz column is written in the session (UTC) zone — normalize
     * to UTC explicitly at the API boundary (same as LinkCreator).
     */
    private function parseInstant(?string $value): ?CarbonImmutable
    {
        return $value === null ? null : CarbonImmutable::parse($value)->utc();
    }
}
