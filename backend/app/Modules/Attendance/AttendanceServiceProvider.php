<?php

namespace App\Modules\Attendance;

use Illuminate\Support\ServiceProvider;
use App\Modules\Shared\Contracts\AttendanceServiceInterface;
use App\Modules\Attendance\Domain\Services\AttendanceService;

class AttendanceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bind interface to implementation
        $this->app->bind(
            AttendanceServiceInterface::class,
            AttendanceService::class
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


