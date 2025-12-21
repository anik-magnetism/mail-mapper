<?php

use Illuminate\Support\Facades\Route;
use AnikNinja\MailMapper\Http\Controllers\Api\EmailMappingController;

Route::resource('email-mappings', EmailMappingController::class)->middleware('auth:api');
