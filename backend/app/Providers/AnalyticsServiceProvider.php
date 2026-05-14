<?php

namespace App\Providers;

use App\Services\Analytics\AnomalyService;
use App\Services\Analytics\ForecastResolver;
use App\Services\Analytics\ForecastService;
use Illuminate\Support\ServiceProvider;
class AnalyticsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Аномалии
        $this->app->singleton(AnomalyService::class, function ($app) {
            return new AnomalyService();
        });

        // Прогнозирование
        $this->app->singleton(ForecastResolver::class, function ($app) {
            return new ForecastResolver();
        });

        $this->app->singleton(ForecastService::class, function ($app) {
            return new ForecastService($app->make(ForecastResolver::class));
        });
    }

    public function boot(): void
    {
        //
    }
}