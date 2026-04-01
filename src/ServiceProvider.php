<?php


namespace StackTrace\Inspec;


use Illuminate\Support\ServiceProvider as Provider;

class ServiceProvider extends Provider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/inspec.php', 'inspec');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/inspec.php' => config_path('inspec.php'),
            ], 'inspec-config');

            $this->commands([
                Commands\Generate::class,
            ]);
        }
    }
}
