<?php

use App\Providers\AppServiceProvider;
use App\Providers\CodeStrategyServiceProvider;
use App\Providers\ConditionServiceProvider;
use App\Providers\Filament\MainPanelProvider;
use App\Providers\HashidsServiceProvider;
use App\Providers\HorizonServiceProvider;

return [
    AppServiceProvider::class,
    CodeStrategyServiceProvider::class,
    ConditionServiceProvider::class,
    MainPanelProvider::class,
    HashidsServiceProvider::class,
    HorizonServiceProvider::class,
];
