<?php

namespace Bjerke\Bread;

use Illuminate\Support\ServiceProvider;

class BreadServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([
            __DIR__ . '/config/bread.php' => config_path('bread.php'),
        ]);
    }

    /**
     * Register bindings in the container.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/config/bread.php', 'bread');
    }
}
