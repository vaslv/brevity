<?php

namespace App\Http\Requests;

use App\Models\Service;
use App\Services\Links\Conditions\ConditionRegistry;
use App\Services\Links\TransitionMode;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'domain' => ['nullable', 'string', 'max:255', 'exists:domains,value'],
            'title' => ['nullable', 'string', 'max:255'],
            'forward_query' => ['nullable', 'boolean'],
            'callback_data' => ['nullable', 'array', 'max:50'],
            'rules' => ['required', 'array', 'min:1', 'max:50'],
            'rules.*.url' => ['required', 'url:http,https', 'max:2048'],
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
