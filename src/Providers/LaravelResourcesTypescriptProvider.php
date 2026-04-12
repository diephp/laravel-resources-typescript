<?php

declare(strict_types=1);

namespace DiePHP\LaravelResourcesTypescript\Providers;

use DiePHP\LaravelResourcesTypescript\Commands\LaravelResourcesTypescriptCommand;
use Illuminate\Support\ServiceProvider;

class LaravelResourcesTypescriptProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/resources2typescript.php',
            'resources2typescript',
        );

        if ($this->app->runningInConsole()) {
            $this->commands([
                LaravelResourcesTypescriptCommand::class,
            ]);
        }
    }

    public function boot(): void
    {
        if (!$this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__ . '/../config/resources2typescript.php' => config_path('resources2typescript.php'),
        ], 'resources2typescript');
    }
}
