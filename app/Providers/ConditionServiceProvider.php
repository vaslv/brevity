<?php

namespace App\Providers;

use App\Services\Links\Conditions\ConditionRegistry;
use App\Services\Links\Conditions\TimeBeforeConditionHandler;
use Illuminate\Support\ServiceProvider;

class ConditionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->tag([
            TimeBeforeConditionHandler::class,
        ], 'condition.handler');

        $this->app->singleton(ConditionRegistry::class, function ($app) {
            return new ConditionRegistry($app->tagged('condition.handler'));
        });
    }
}
