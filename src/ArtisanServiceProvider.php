<?php

namespace DddLaravel;

use Illuminate\Support\ServiceProvider;

class ArtisanServiceProvider extends ServiceProvider
{
    protected $commands = [
        'DddLaravel\Commands\DumpDependeciesMakeCommand',
        'DddLaravel\Commands\InterfaceMakeCommand',
        'DddLaravel\Commands\ThingMakeCommand',
    ];


    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        /*if ($this->app->runningInConsole()) {
            $this->commands([
                TestmeCommand::class,
            ]);
        }*/
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        $this->commands($this->commands);
    }
}