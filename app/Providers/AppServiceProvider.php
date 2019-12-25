<?php

namespace App\Providers;

use App\Contracts\SiteDetector;
use App\Services\SiteDetector\FakeSiteDetector;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {

        /*
         * Строим сервис.
         */
        $this->app->singleton(FakeSiteDetector::class, function () {
            return new FakeSiteDetector();
        });

        /*
         * Биндим контракт
         */
        $this->app->bind(SiteDetector::class, FakeSiteDetector::class);
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}
