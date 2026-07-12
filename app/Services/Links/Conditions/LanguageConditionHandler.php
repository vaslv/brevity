<?php

namespace App\Services\Links\Conditions;

use App\Models\Condition;
use Symfony\Component\HttpFoundation\AcceptHeader;

/**
 * Matches when the visitor's Accept-Language header strongly prefers the
 * configured language (optionally scoped to a country). "Strongly" = quality
 * ≥ 0.9, so a language the browser lists only as a low-priority fallback does
 * not trigger the rule. An empty header or `*` never matches.
 */
final class LanguageConditionHandler extends AbstractConditionHandler
{
    private const QUALITY_THRESHOLD = 0.9;

    public function matches(Condition $condition, ConditionContext $context): bool
    {
        $language = $condition->data['language'] ?? null;
        $country = $condition->data['country'] ?? null;

        if (! is_string($language) || $language === '') {
            return false;
        }

        $header = $context->request->headers->get('Accept-Language');

        if ($header === null || $header === '') {
            return false;
        }

        $target = strtolower($country !== null && $country !== '' ? "{$language}-{$country}" : $language);

        foreach (AcceptHeader::fromString($header)->all() as $item) {
            $value = strtolower($item->getValue());

            if ($value === '*' || $item->getQuality() < self::QUALITY_THRESHOLD) {
                continue;
            }

            // Country-scoped condition needs an exact language-country match;
            // a language-only condition matches on the language subtag alone.
            $matches = $country !== null && $country !== ''
                ? $value === $target
                : explode('-', $value)[0] === $target;

            if ($matches) {
                return true;
            }
        }

        return false;
    }

    public static function rules(): array
    {
        return [
            'language' => ['required', 'string', 'regex:/^[a-zA-Z]{2,3}$/'],
            'country' => ['nullable', 'string', 'regex:/^[a-zA-Z]{2}$/'],
        ];
    }
}
