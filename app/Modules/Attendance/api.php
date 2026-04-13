<?php

use App\Modules\Attendance\Controllers\AdminAttendanceController;
use App\Modules\Attendance\Controllers\AdminProfileController;
use App\Modules\Attendance\Controllers\AttendanceController;
use App\Modules\Attendance\Controllers\FaceVerificationController;
use App\Modules\Attendance\Controllers\OfficeController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'check.account_status', 'check.account_locked'])->group(function () {
    // Office list — all authenticated users can select an active working location.
    Route::get('/offices', [OfficeController::class, 'index']);

    // Office management — restricted to elevated roles.
    Route::middleware('role:top_management|hr_admin')->group(function () {
        Route::post('/offices', [OfficeController::class, 'store']);
        Route::put('/offices/{office}', [OfficeController::class, 'update']);
        Route::patch('/offices/{office}', [OfficeController::class, 'toggleActive']);
    });

    // Employee self‑service
    Route::get('/attendance/my-today', [AttendanceController::class, 'myToday']);
    Route::post('/attendance/upload-photo', [AttendanceController::class, 'uploadPhoto']);
    Route::post('/attendance/logs', [AttendanceController::class, 'store']);

    // Admin analytics + staff data (top_management, hr_admin, hod — HOD scope applied in controller for profiles)
    Route::middleware('role:top_management|hr_admin|hod')->group(function () {
        Route::get('/admin/attendance/today', [AdminAttendanceController::class, 'today']);
        Route::get('/admin/profiles', [AdminProfileController::class, 'index']);
    });

    // Face verification
    Route::post('/face/verify', [FaceVerificationController::class, 'verify']);
});
