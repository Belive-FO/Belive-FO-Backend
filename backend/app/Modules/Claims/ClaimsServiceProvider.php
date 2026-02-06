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
        if (file_exists(__DIR__ . '/Presentation/Http/routes.php')) {
            $this->loadRoutesFrom(__DIR__ . '/Presentation/Http/routes.php');
        }
        
        // Load migrations if they exist
        if (is_dir(__DIR__ . '/Infrastructure/Persistence/Migrations')) {
            $this->loadMigrationsFrom(__DIR__ . '/Infrastructure/Persistence/Migrations');
        }
    }
}


