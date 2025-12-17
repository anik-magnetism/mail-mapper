<?php

namespace AnikNinja\MailMapper;

use Illuminate\Support\ServiceProvider;

class MailMapperServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/mail-mapper.php', 'mail-mapper');
    }

    public function boot()
    {
        // Migrations, views & config publish
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'mailmapper');

        $this->publishes([
            __DIR__ . '/../config/mail-mapper.php' => config_path('mail-mapper.php'),
        ], 'config');

        $this->publishes([
            __DIR__ . '/../resources/views' => resource_path('views'),
        ], 'views');

        $this->publishes([
            __DIR__ . '/../database/migrations/' => database_path('migrations'),
        ], 'migrations');
    }
}
