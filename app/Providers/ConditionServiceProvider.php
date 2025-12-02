<?php

namespace App\Providers;

use App\Services\Links\Conditions\ConditionRegistry;
use App\Services\Links\Conditions\TimeBeforeConditionHandler;
use Illuminate\Support\ServiceProvider;

class ConditionServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(ConditionRegistry::class, function ($app) {
            return new ConditionRegistry([
                $app->make(TimeBeforeConditionHandler::class),
            ]);
        });
    }
}
