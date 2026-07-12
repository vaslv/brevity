<?php

namespace App\Services\Links\Conditions;

use App\Models\Condition;
use Illuminate\Validation\Rule;

/**
 * Matches when the visitor's device belongs to the configured type. One user
 * agent can satisfy several types (an iPhone matches both `ios` and `mobile`),
 * so a rule keyed on `mobile` catches every mobile OS.
 */
final class DeviceConditionHandler extends AbstractConditionHandler
{
    public const TYPES = ['android', 'ios', 'mobile', 'windows', 'macos', 'linux', 'chromeos', 'desktop'];

    public function __construct(
        private readonly DeviceTypeDetector $deviceTypeDetector,
    ) {}

    public function matches(Condition $condition, ConditionContext $context): bool
    {
        $device = $condition->data['device'] ?? null;

        if (! is_string($device)) {
            return false;
        }

        return in_array($device, $this->deviceTypeDetector->typesFor($context->request->userAgent()), true);
    }

    public static function rules(): array
    {
        return [
            'device' => ['required', 'string', Rule::in(self::TYPES)],
        ];
    }
}
