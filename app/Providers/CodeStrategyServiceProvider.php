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
        // Only one strategy exists today. The CodeGenerator interface is the
        // extension seam — bind a different implementation here (optionally
        // branching on config('link.code_strategy')) to add another.
        $this->app->singleton(CodeGenerator::class, fn ($app) => $app->make(HashidCodeGenerator::class));
    }
}
