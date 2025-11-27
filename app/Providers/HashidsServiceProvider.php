<?php

namespace App\Providers;

use Hashids\Hashids;
use Illuminate\Support\ServiceProvider;

class HashidsServiceProvider extends ServiceProvider
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
        $this->app->singleton(Hashids::class, function ($app) {
            $config = $app['config']['hashids'];

            return new Hashids(
                $config['salt'],
                $config['length'],
                $config['alphabet']
            );
        });
    }
}
