<?php

namespace Convenia\Timeliner;

use Illuminate\Support\ServiceProvider;

class TimelinerServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot()
    {
        $this->app->register('BaoPham\DynamoDb\DynamoDbServiceProvider');
        $this->publishes([
            __DIR__.'/config/mirrorable-config.php',
        ]);
    }

    /**
     * Register the application services.
     */
    public function register()
    {
        $this->app->register('BaoPham\DynamoDb\DynamoDbServiceProvider');
    }
}
