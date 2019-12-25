<?php

declare(strict_types=1);

namespace App\Providers;

use App\Custom\Illuminate\Foundation\Console\RouteCacheCommand;
use App\Custom\Illuminate\Foundation\Console\RouteListCommand;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;

class CommandsReplaceProvider extends ServiceProvider implements DeferrableProvider
{

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('command.route.cache', function (Application $app) {
            return new RouteCacheCommand($app->get('files'));
        });

        $this->app->singleton('command.route.list', function (Application $app) {
            return new RouteListCommand($app->get('router'));
        });
        $this->commands($this->provides());
    }

    public function provides()
    {
        return [
            'command.route.cache',
            'command.route.list',
        ];
    }
}
