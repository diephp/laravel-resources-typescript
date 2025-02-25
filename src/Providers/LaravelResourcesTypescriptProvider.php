<?php

namespace DiePHP\LaravelResourcesTypescript\Providers;

use DiePHP\LaravelResourcesTypescript\Commands\LaravelResourcesTypescriptCommand;
use Illuminate\Support\ServiceProvider;

/**
 * This service provider handles the registration of commands and the publishing of configuration files
 * related to the LaravelResourcesTypescript package.
 */
class LaravelResourcesTypescriptProvider extends ServiceProvider
{
    public function register()
    {
        $this->commands([
            LaravelResourcesTypescriptCommand::class,
        ]);

    }

    public function boot ()
    {
        $this->publishConfigs();
    }


    protected function publishConfigs(): void
    {
        $this->publishes([
            __DIR__ . '/../config/resources2typescript.php' => config_path('resources2typescript.php'),
        ], 'resources2typescript');

    }


}
