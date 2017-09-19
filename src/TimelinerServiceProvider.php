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
        $this->publishes([
            __DIR__.'/config/mirrorable.php',
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
