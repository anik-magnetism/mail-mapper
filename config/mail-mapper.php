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
];
