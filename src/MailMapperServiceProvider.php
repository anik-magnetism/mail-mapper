<?php

namespace AnikNinja\MailMapper;

use AnikNinja\MailMapper\Contracts\EmailMappingRepositoryContract;
use AnikNinja\MailMapper\Repositories\EmailMappingRepository;
use Illuminate\Support\ServiceProvider;

/**
 * Class MailMapperServiceProvider
 *
 * Registers and boots the Mail Mapper package for Laravel.
 * - Merges package config
 * - Loads and publishes migrations, views, and config
 *
 * Usage:
 *   // Automatically discovered by Laravel if installed via Composer
 *   // or add to config/app.php providers array:
 *   AnikNinja\MailMapper\MailMapperServiceProvider::class,
 */
class MailMapperServiceProvider extends ServiceProvider
{
    /**
     * Register package services and merge configuration.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/mail-mapper.php', 'mail-mapper');

        $this->app->singleton(
            EmailMappingRepositoryContract::class,
            EmailMappingRepository::class
        );
    }

    /**
     * Bootstrap package resources: migrations, views, and publishable files.
     *
     * @return void
     */
    public function boot()
    {
        // Load API routes
        $this->loadRoutesFrom(__DIR__ . '/routes/api.php');

        // Migrations, views & config publish
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'mailmapper');

        // Publishable config file
        $this->publishes([
            __DIR__ . '/../config/mail-mapper.php' => config_path('mail-mapper.php'),
        ], 'config');

        // Publish views
        $this->publishes([
            __DIR__ . '/../resources/views' => resource_path('views'),
        ], 'views');

        // Publish migrations
        $this->publishes([
            __DIR__ . '/../database/migrations/' => database_path('migrations'),
        ], 'migrations');
    }
}
