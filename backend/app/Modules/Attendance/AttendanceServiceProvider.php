<?php

namespace App\Modules\Attendance;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use App\Modules\Shared\Contracts\AttendanceServiceInterface;
use App\Modules\Attendance\Services\AttendanceService;

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
        // Load routes under /api so they match frontend proxy (e.g. POST /api/offices)
        if (file_exists(__DIR__ . '/Controllers/routes.php')) {
            Route::prefix('api')->middleware('api')->group(__DIR__ . '/Controllers/routes.php');
        }
        
        // Load migrations if they exist
        if (is_dir(__DIR__ . '/Migrations')) {
            $this->loadMigrationsFrom(__DIR__ . '/Migrations');
        }
    }
}


