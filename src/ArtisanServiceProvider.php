<?php

namespace DddLaravel;

use Illuminate\Support\ServiceProvider;

class ArtisanServiceProvider extends ServiceProvider
{
    protected $commands = [
        'DddLaravel\Commands\DumpDependeciesMakeCommand',
        'DddLaravel\Commands\InterfaceMakeCommand',
        'DddLaravel\Commands\ThingMakeCommand',
        'DddLaravel\Commands\EndPointMakeCommand',
        'DddLaravel\Commands\InjectionsMakeCommand',
        'DddLaravel\Commands\InjectionsUpdateCommand',
    ];


    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        //
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