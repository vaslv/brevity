<?php

namespace App\Services\Links\Conditions;

use App\Models\Condition;
use InvalidArgumentException;

readonly class ConditionFactory
{
    public function __construct(
        private ConditionRegistry $registry,
    ) {}

    public function create(array $payload): Condition
    {
        $type = $payload['type'] ?? null;

        if (! $type) {
            throw new InvalidArgumentException('Condition "type" is required.');
        }

        $data = $this->registry->validate($type, $payload['data'] ?? []);

        return Condition::firstOrCreate([
            'type' => $type,
            'data' => $data,
        ]);
    }
}
