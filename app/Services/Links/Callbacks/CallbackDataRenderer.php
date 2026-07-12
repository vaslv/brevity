<?php

namespace App\Services\Links\Callbacks;

use App\Models\Click;

readonly class CallbackDataRenderer
{
    public function render(array $template, Click $click): array
    {
        $variables = $this->buildVariables($click);

        return $this->renderValue($template, $variables);
    }

    /**
     * @return array<string, string>
     */
    private function buildVariables(Click $click): array
    {
        return [
            'click.id' => (string) $click->id,
            'click.created_at' => $click->created_at->toIso8601String(),
            'click.is_bot' => ($click->userAgent?->is_bot ?? false) ? 'true' : 'false',
            'click.ip' => $click->ipAddress?->value ?? '',
            'click.url' => $click->url->value,
            'click.referrer' => $click->referrer?->value ?? '',
            'click.user_agent' => $click->userAgent?->value ?? '',
            'click.variant' => $this->variantLabel($click),
            ...$this->queryVariables($click),
            'link.id' => (string) $click->link_id,
            'link.code' => (string) $click->link->code,
            'link.title' => $click->link->title ?? '',
        ];
    }

    /**
     * The visit's own query params (GAP-03): {{click.query.<param>}} gives the
     * partner its sub-ids back in the postback. A missing param renders as an
     * empty string (handled in renderString).
     *
     * Split by hand instead of parse_str: PHP variable-name rules make
     * parse_str mangle `.`, space and `[` in param names into `_`, so a
     * partner's `{{click.query.sub.id}}` could never match its stored
     * `sub_id` key and would silently post '' forever. Names stay literal
     * here; a repeated name keeps the last value (as parse_str did).
     *
     * @return array<string, string>
     */
    private function queryVariables(Click $click): array
    {
        if ($click->visited_query === null) {
            return [];
        }

        $variables = [];

        foreach (explode('&', $click->visited_query) as $pair) {
            [$key, $value] = array_pad(explode('=', $pair, 2), 2, '');
            $key = $this->scrub(urldecode($key));

            if ($key !== '') {
                $variables['click.query.'.$key] = $this->scrub(urldecode($value));
            }
        }

        return $variables;
    }

    private function renderString(string $template, array $variables): string
    {
        // Hyphens are allowed for the sake of {{click.query.sub-id}} — query
        // param names commonly carry them.
        return preg_replace_callback(
            '/\{\{\s*([a-zA-Z][a-zA-Z0-9_.-]*)\s*\}\}/',
            static function (array $matches) use ($variables): string {
                // Contract (docs/03-api.md §10): an absent query param renders
                // as an empty string; any other unknown placeholder is left
                // verbatim (it may be the partner's own syntax).
                if (str_starts_with($matches[1], 'click.query.')) {
                    return $variables[$matches[1]] ?? '';
                }

                return $variables[$matches[1]] ?? $matches[0];
            },
            $template
        );
    }

    private function renderValue(mixed $value, array $variables): mixed
    {
        if (is_string($value)) {
            return $this->renderString($value, $variables);
        }

        if (is_array($value)) {
            return array_map(fn (mixed $item) => $this->renderValue($item, $variables), $value);
        }

        return $value;
    }

    /**
     * The stored query string is byte-capped, so a percent-encoded multibyte
     * sequence may be cut mid-way; after urldecode that yields invalid UTF-8,
     * which Postgres jsonb (callbacks.data) rejects. Scrub keys and values the
     * same way ClickRecorder does.
     */
    private function scrub(string $value): string
    {
        return str_replace("\0", '', mb_convert_encoding($value, 'UTF-8', 'UTF-8'));
    }

    /**
     * The A/B variant a click resolved to (GAP-04): the partner needs to know
     * which arm converted. Its label, or an empty string when the rule ran no
     * split (or the variant was since removed).
     */
    private function variantLabel(Click $click): string
    {
        return $click->ruleVariant?->label ?? '';
    }
}
