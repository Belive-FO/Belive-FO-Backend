<?php

namespace App\Modules\Claims;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class ClaimsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register claims module bindings here
    }

    public function boot(): void
    {
        // Load routes under /api so they match frontend proxy
        if (file_exists(__DIR__.'/api.php')) {
            Route::prefix('api')->middleware('api')->group(__DIR__.'/api.php');
        }

        // Load migrations if they exist
        if (is_dir(__DIR__.'/Migrations')) {
            $this->loadMigrationsFrom(__DIR__.'/Migrations');
        }
    }
}


