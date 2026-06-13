<?php

namespace App\Services\Links\Domains;

use App\Services\Links\Domains\Strategies\DomainSelectionStrategyHandler;
use InvalidArgumentException;

class DomainStrategyRegistry
{
    /**
     * @var array<string, DomainSelectionStrategyHandler>
     */
    private array $handlers = [];

    /**
     * @param  iterable<DomainSelectionStrategyHandler>  $handlers
     */
    public function __construct(iterable $handlers)
    {
        foreach ($handlers as $handler) {
            $this->handlers[$handler::strategy()->value] = $handler;
        }
    }

    public function handlerFor(DomainSelectionStrategy $strategy): DomainSelectionStrategyHandler
    {
        return $this->handlers[$strategy->value]
            ?? throw new InvalidArgumentException("No handler registered for domain strategy [{$strategy->value}].");
    }
}
