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
            'link.id' => (string) $click->link_id,
            'link.code' => (string) $click->link->code,
            'link.title' => $click->link->title ?? '',
        ];
    }

    private function renderString(string $template, array $variables): string
    {
        return preg_replace_callback(
            '/\{\{\s*([a-zA-Z][a-zA-Z0-9_.]*)\s*\}\}/',
            static fn (array $matches): string => $variables[$matches[1]] ?? $matches[0],
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
}
