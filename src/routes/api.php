<?php

use Illuminate\Support\Facades\Route;
use AnikNinja\MailMapper\Http\Controllers\Api\EmailMappingController;

// Use published config to allow host app to override prefix, middleware and version
$apiConfig = config('mail-mapper.api', []);
$prefix = $apiConfig['prefix'] ?? 'api';
$version = $apiConfig['version'] ?? null;
$middleware = $apiConfig['middleware'] ?? ['api', 'auth:api'];

$fullPrefix = $prefix;
if (!empty($version)) {
    $fullPrefix = trim($prefix, '/') . '/' . trim($version, '/');
}

Route::group(['prefix' => $fullPrefix, 'middleware' => $middleware], function () {
    Route::resource('email-mappings', EmailMappingController::class);
});
