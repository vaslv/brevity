<?php

namespace App\Http\Requests;

use App\Models\Service;
use App\Services\Links\Conditions\ConditionRegistry;
use App\Services\Links\Domains\DomainSelectionStrategy;
use App\Services\Links\TransitionMode;
use App\Services\Links\UrlNormalizer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use League\Uri\Exceptions\SyntaxError;

class StoreLinkRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() instanceof Service;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        $conditionRegistry = app(ConditionRegistry::class);

        return [
            // An explicit domain is mutually exclusive with automatic selection.
            'domain' => ['nullable', 'string', 'max:255', 'exists:domains,value', 'prohibits:domain_strategy,domain_group'],
            // Auto-select a domain by strategy; required when a group scopes it.
            'domain_strategy' => ['nullable', 'string', 'required_with:domain_group', Rule::in(DomainSelectionStrategy::values())],
            // Optional scope for the strategy (group code); without it selection spans all domains.
            'domain_group' => ['nullable', 'string', 'exists:domain_groups,code'],
            'title' => ['nullable', 'string', 'max:64'],
            'forward_query' => ['nullable', 'boolean'],
            'callback_data' => ['nullable', 'array', 'max:50'],
            // Lifecycle limits (docs/03-api.md §5): same strict ISO 8601 format
            // as condition dates; NULL = no limit. The window edges are
            // inclusive; a zero-length window (since == until) is allowed.
            'valid_since' => ['nullable', 'date_format:Y-m-d\TH:i:sP'],
            'valid_until' => [
                'nullable',
                'date_format:Y-m-d\TH:i:sP',
                ...($this->filled('valid_since') ? ['after_or_equal:valid_since'] : []),
            ],
            // max: PG integer ceiling — an over-limit value must 422, not 500.
            'max_clicks' => ['nullable', 'integer', 'min:1', 'max:2147483647'],
            'rules' => ['required', 'array', 'min:1', 'max:50'],
            'rules.*.url' => self::destinationUrlRules(),
            // A rule matches when ALL its conditions match (RUL-01). Capped to
            // keep a single rule's AND-set bounded.
            'rules.*.conditions' => ['nullable', 'array', 'max:10'],
            'rules.*.conditions.*.type' => [
                'required',
                'string',
                'max:32',
                Rule::in($conditionRegistry->types()),
            ],
            'rules.*.conditions.*.data' => ['nullable', 'array'],
            // A/B split (GAP-04): at least 2 weighted targets. No variants =
            // the rule uses its own url as before.
            'rules.*.variants' => ['nullable', 'array', 'min:2', 'max:20'],
            // Variants write to urls.value like the primary url — same byte cap.
            'rules.*.variants.*.url' => self::destinationUrlRules(),
            'rules.*.variants.*.weight' => ['required', 'integer', 'min:1', 'max:1000'],
            'rules.*.variants.*.label' => ['nullable', 'string', 'max:64'],
            'rules.*.transition_mode' => [
                'nullable',
                'string',
                'max:16',
                Rule::in(TransitionMode::values()),
            ],
            ...$this->conditionDataRules($conditionRegistry),
        ];
    }

    /**
     * Backward compatibility (RUL-01): a rule may carry a single `condition`
     * object OR a `conditions` list. Fold the legacy singular form into the
     * canonical list here so validation and persistence only deal with lists.
     */
    protected function prepareForValidation(): void
    {
        $rules = $this->input('rules');

        if (! is_array($rules)) {
            return;
        }

        foreach ($rules as $index => $rule) {
            if (is_array($rule) && ! array_key_exists('conditions', $rule) && isset($rule['condition'])) {
                $rules[$index]['conditions'] = [$rule['condition']];
                unset($rules[$index]['condition']);
            }
        }

        $this->merge(['rules' => $rules]);
    }

    /**
     * @return array<string, ValidationRule|array|string>
     */
    private function conditionDataRules(ConditionRegistry $conditionRegistry): array
    {
        $rules = $this->input('rules', []);

        if (! is_array($rules)) {
            return [];
        }

        $validationRules = [];

        foreach ($rules as $index => $rule) {
            if (! is_array($rule) || ! is_array($rule['conditions'] ?? null)) {
                continue;
            }

            foreach ($rule['conditions'] as $conditionIndex => $condition) {
                $type = data_get($condition, 'type');

                if (! is_string($type)) {
                    continue;
                }

                $handler = $conditionRegistry->getHandler($type);

                if (! $handler) {
                    continue;
                }

                foreach ($handler::rules() as $field => $fieldRules) {
                    $validationRules["rules.$index.conditions.$conditionIndex.data.$field"] = $fieldRules;
                }
            }
        }

        return $validationRules;
    }

    /**
     * A destination URL (rule target or A/B variant): a web scheme, byte-capped
     * so it fits the urls.value UNIQUE index. The cap is measured on the
     * NORMALIZED value (the form actually persisted), because percent-encoding
     * an internationalized URL can multiply its byte length — a raw-length cap
     * would let it pass validation and then overflow the column at write time.
     *
     * @return array<int, ValidationRule|string|\Closure>
     */
    private static function destinationUrlRules(): array
    {
        return [
            'required',
            'url:http,https',
            'max:2048',
            static function (string $attribute, mixed $value, \Closure $fail): void {
                if (! is_string($value)) {
                    return;
                }

                try {
                    $normalized = UrlNormalizer::normalize($value);
                } catch (SyntaxError) {
                    // Not a parseable URL — the url:http,https rule already fails it.
                    return;
                }

                if (strlen($normalized) > UrlNormalizer::MAX_BYTES) {
                    $fail('The destination URL must not exceed '.UrlNormalizer::MAX_BYTES.' bytes once normalized.');
                }
            },
        ];
    }
}
