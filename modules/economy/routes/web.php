<?php

use Illuminate\Support\Facades\Route;

/**
 * Plugin Web Routes
 * 
 * These routes are automatically loaded by the MKS CMS plugin system.
 * They are wrapped in the 'web' middleware group.
 */

Route::get('/economy-example', function () {
    return 'Hello from economy plugin!';
});