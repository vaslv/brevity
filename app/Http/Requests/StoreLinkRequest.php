<?php

namespace App\Http\Requests;

use App\Models\Service;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

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
        return [
            'domain' => ['nullable', 'string', 'max:255', 'exists:domains,value'],
            'title' => ['nullable', 'string', 'max:255'],
            'forward_query' => ['nullable', 'boolean'],
            'callback_data' => ['nullable', 'array'],
            'rules' => ['required', 'array', 'min:1'],
            'rules.*.url' => ['required', 'url', 'max:2048'],
            'rules.*.condition' => ['nullable', 'array'],
            'rules.*.condition.type' => ['required_with:rules.*.condition', 'string', 'max:32'],
            'rules.*.condition.data' => ['nullable', 'array'],
        ];
    }
}
