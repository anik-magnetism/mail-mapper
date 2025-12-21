<?php

/**
 * Mail Mapper Configuration File
 * This file contains configuration settings for the Mail Mapper package,
 * including default sender information and logging options.
 * You can publish this configuration file to your Laravel application's
 * config directory using the following Artisan command:
 * php artisan vendor:publish --provider="AnikNinja\MailMapper\MailMapperServiceProvider" --tag="config"
 * After publishing, you can modify the settings as needed.
 */

use PSpell\Config;

return [
    // Default "from" address and name for outgoing emails
    /** 
     * 'address' => string The email address to use as the sender.
     * 'name' => string The name to use as the sender.
     */
    'default_from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'no-reply@example.com'),
        'name' => env('MAIL_FROM_NAME', 'No Reply'),
    ],

    // Use raw/direct mail sending as fallback if normal sending fails
    /**
     * If the configuration is set to false, the caught mail error is re-thrown,
     * preventing the use of a raw fallback mechanism for sending emails.
     * If the configuration is true (or not set, defaults to true), the fallback is allowed.
     * Defaults to true.
     */
    'use_raw_fallback' => env('MAIL_MAPPER_USE_RAW_FALLBACK', true),

    // Enable logging of email attachment info
    /**
     * If set to true, logs information about email attachments such as filename, size, and MIME type.
     * This is useful for debugging purposes.
     * Defaults to true.
     */
    'enable_logging' => env('MAIL_MAPPER_ENABLE_LOGGING', true),

    // User model for relations
    /**
     * Specify the User model class used in the application.
     * This is used for relationships in the EmailMapping model.
     */
    'user_model' => config('auth.providers.users.model', \App\Models\User::class), // Change default as needed

    // API settings for the package routes
    /**
     * Configuration settings for the Mail Mapper API routes.
     * - 'prefix': The route prefix for all API routes.
     * - 'version': Optional version segment for the API routes.
     * - 'middleware': Middleware applied to the API routes.
     * - 'per_page': Default pagination size for listing endpoints.
     * - 'max_per_page': Maximum allowed pagination size to prevent abuse.
     */
    'api' => [
        'prefix' => env('MAIL_MAPPER_API_PREFIX', 'api'),
        'version' => env('MAIL_MAPPER_API_VERSION', null),
        'middleware' => ['api', 'auth:api'],
        'per_page' => env('MAIL_MAPPER_API_PER_PAGE', 15),
        'max_per_page' => env('MAIL_MAPPER_API_MAX_PER_PAGE', 100),
    ],
];
