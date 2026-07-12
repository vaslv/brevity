<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;

/**
 * PATCH semantics on top of the create rules: every field is optional
 * (`sometimes`), so validated() contains exactly the keys the client sent —
 * the sentinel LinkUpdater needs to distinguish "absent" from "null".
 * Immutable fields (domain, strategy, group) are not accepted at all.
 */
class UpdateLinkRequest extends StoreLinkRequest
{
    /**
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        $rules = parent::rules();

        // Immutable on update by design: code, domain, service.
        unset($rules['domain'], $rules['domain_strategy'], $rules['domain_group']);

        // The rule set is optional on PATCH but, when present, replaces the
        // whole ordered set (same constraints as on create).
        $rules['rules'] = ['sometimes', 'array', 'min:1', 'max:50'];

        foreach (['title', 'forward_query', 'callback_data', 'valid_since', 'valid_until', 'max_clicks'] as $field) {
            $rules[$field] = array_merge(['sometimes'], (array) $rules[$field]);
        }

        return $rules;
    }
}
