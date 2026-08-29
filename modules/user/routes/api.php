<?php

use Illuminate\Support\Facades\Route;

/**
 * Plugin API Routes
 * 
 * These routes are automatically loaded by the MKS CMS plugin system.
 * They are wrapped in the 'api' middleware group and prefixed with 'api/'.
 */

Route::get('/user-test', function () {
    return ['status' => 'success', 'message' => 'API is working'];
});