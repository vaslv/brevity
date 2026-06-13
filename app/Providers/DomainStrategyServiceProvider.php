<?php

namespace App\Providers;

use App\Services\Links\Domains\DomainStrategyRegistry;
use App\Services\Links\Domains\Strategies\ColdestDomainStrategy;
use App\Services\Links\Domains\Strategies\RandomDomainStrategy;
use App\Services\Links\Domains\Strategies\RoundRobinDomainStrategy;
use Illuminate\Support\ServiceProvider;

class DomainStrategyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Add a strategy by implementing DomainSelectionStrategyHandler and
        // tagging it here — the registry picks it up automatically.
        $this->app->tag([
            RandomDomainStrategy::class,
            RoundRobinDomainStrategy::class,
            ColdestDomainStrategy::class,
        ], 'domain.strategy');

        $this->app->singleton(DomainStrategyRegistry::class, function ($app) {
            return new DomainStrategyRegistry($app->tagged('domain.strategy'));
        });
    }
}
