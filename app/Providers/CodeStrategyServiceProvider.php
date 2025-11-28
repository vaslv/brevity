<?php

namespace App\Providers;

use App\Services\Links\CodeStrategy\CodeGenerator;
use App\Services\Links\CodeStrategy\HashidCodeGenerator;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;

class CodeStrategyServiceProvider extends ServiceProvider implements DeferrableProvider
{
    public function provides(): array
    {
        return [CodeGenerator::class];
    }

    public function register(): void
    {
        $this->app->singleton(CodeGenerator::class, function ($app) {
            $strategy = config('link.code_strategy', 'hashid');

            /** @noinspection PhpDuplicateMatchArmBodyInspection */
            return match ($strategy) {
                'hashid' => $app->make(HashidCodeGenerator::class),
                default => $app->make(HashidCodeGenerator::class),
            };
        });
    }
}
