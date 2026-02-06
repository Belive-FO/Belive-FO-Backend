<?php

namespace App\Modules\Leave;

use Illuminate\Support\ServiceProvider;
use App\Modules\Shared\Contracts\LeaveServiceInterface;
use App\Modules\Leave\Domain\Services\LeaveService;

class LeaveServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bind interface to implementation
        $this->app->bind(
            LeaveServiceInterface::class,
            LeaveService::class
        );
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


