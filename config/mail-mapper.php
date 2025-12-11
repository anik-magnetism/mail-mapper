<?php
return [
    'default_from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'no-reply@example.com'),
        'name' => env('MAIL_FROM_NAME', 'No Reply'),
    ],
    'use_raw_fallback' => true,
];
