<?php

namespace App\Http\Requests;

use App\Models\Service;
use App\Services\Links\Conditions\ConditionRegistry;
use App\Services\Links\Domains\DomainSelectionStrategy;
use App\Services\Links\TransitionMode;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLinkRequest extends FormRequest
{
    /**
     * Max byte length for a destination URL. `urls.value` carries a UNIQUE btree
     * index (~2704-byte key limit); an over-long URL is rejected (422) rather
     * than truncated, since truncating a redirect target would corrupt it.
     */
    private const MAX_URL_BYTES = 2000;

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
            'domain' => ['nullable', 'string', 'max:255', 'exists:domains,value', 'prohibits:domain_strategy,domain_group_id'],
            // Auto-select a domain by strategy; required when a group scopes it.
            'domain_strategy' => ['nullable', 'string', 'required_with:domain_group_id', Rule::in(DomainSelectionStrategy::values())],
            // Optional scope for the strategy; without it selection spans all domains.
            'domain_group_id' => ['nullable', 'integer', 'exists:domain_groups,id'],
            'title' => ['nullable', 'string', 'max:64'],
            'forward_query' => ['nullable', 'boolean'],
            'callback_data' => ['nullable', 'array', 'max:50'],
            'rules' => ['required', 'array', 'min:1', 'max:50'],
            'rules.*.url' => [
                'required',
                'url:http,https',
                'max:2048',
                static function (string $attribute, mixed $value, \Closure $fail): void {
                    if (is_string($value) && strlen($value) > self::MAX_URL_BYTES) {
                        $fail('The destination URL must not exceed '.self::MAX_URL_BYTES.' bytes.');
                    }
                },
            ],
            'rules.*.condition' => ['nullable', 'array'],
            'rules.*.condition.type' => [
                'required_with:rules.*.condition',
                'string',
                'max:32',
                Rule::in($conditionRegistry->types()),
            ],
            'rules.*.condition.data' => ['nullable', 'array'],
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
            if (! is_array($rule)) {
                continue;
            }

            $type = data_get($rule, 'condition.type');

            if (! is_string($type)) {
                continue;
            }

            $handler = $conditionRegistry->getHandler($type);

            if (! $handler) {
                continue;
            }

            foreach ($handler::rules() as $field => $fieldRules) {
                $validationRules["rules.$index.condition.data.$field"] = $fieldRules;
            }
        }

        return $validationRules;
    }
}
