<?php

namespace App\Services\Links\Conditions;

use App\Models\Condition;
use RuntimeException;

class ConditionRegistry
{
    /**
     * @var array<string, ConditionHandler>
     */
    private array $handlers = [];

    /**
     * @param  iterable<ConditionHandler>  $handlers
     */
    public function __construct(iterable $handlers)
    {
        foreach ($handlers as $handler) {
            $this->handlers[$handler::type()] = $handler;
        }
    }

    public function getHandler(string $type): ?ConditionHandler
    {
        return $this->handlers[$type] ?? null;
    }

    public function getHandlerFor(Condition $condition): ?ConditionHandler
    {
        return $this->getHandler($condition->type);
    }

    public function types(): array
    {
        $types = array_keys($this->handlers);
        sort($types);

        return $types;
    }

    public function validate(string $type, array $data): array
    {
        $handler = $this->getHandler($type);

        if (! $handler) {
            throw new RuntimeException('Unknown condition type: '.$type);
        }

        return $handler::validate($data);
    }
}
