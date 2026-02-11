<?php

namespace App\Modules\Claims;

use Illuminate\Support\ServiceProvider;

class ClaimsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register claims module bindings here
    }
    
    public function boot(): void
    {
        // Load routes if they exist
        if (file_exists(__DIR__ . '/Controllers/routes.php')) {
            $this->loadRoutesFrom(__DIR__ . '/Controllers/routes.php');
        }
        
        // Load migrations if they exist
        if (is_dir(__DIR__ . '/Migrations')) {
            $this->loadMigrationsFrom(__DIR__ . '/Migrations');
        }
    }
}


