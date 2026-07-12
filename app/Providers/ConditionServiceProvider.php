<?php

namespace App\Providers;

use App\Services\Links\Conditions\AfterDateConditionHandler;
use App\Services\Links\Conditions\ConditionRegistry;
use App\Services\Links\Conditions\DeviceConditionHandler;
use App\Services\Links\Conditions\IpAddressConditionHandler;
use App\Services\Links\Conditions\LanguageConditionHandler;
use App\Services\Links\Conditions\QueryParamConditionHandler;
use App\Services\Links\Conditions\TimeBeforeConditionHandler;
use Illuminate\Support\ServiceProvider;

class ConditionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->tag([
            TimeBeforeConditionHandler::class,
            AfterDateConditionHandler::class,
            QueryParamConditionHandler::class,
            IpAddressConditionHandler::class,
            DeviceConditionHandler::class,
            LanguageConditionHandler::class,
        ], 'condition.handler');

        $this->app->singleton(ConditionRegistry::class, function ($app) {
            return new ConditionRegistry($app->tagged('condition.handler'));
        });
    }
}
