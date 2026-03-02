<?php

use App\Modules\Attendance\Controllers\AdminAttendanceController;
use App\Modules\Attendance\Controllers\AdminProfileController;
use App\Modules\Attendance\Controllers\AttendanceController;
use App\Modules\Attendance\Controllers\FaceVerificationController;
use App\Modules\Attendance\Controllers\OfficeController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    // Employee self‑service
    Route::get('/offices', [OfficeController::class, 'index']);
    Route::get('/attendance/my-today', [AttendanceController::class, 'myToday']);
    Route::post('/attendance/logs', [AttendanceController::class, 'store']);

    // Admin analytics + staff data
    Route::get('/admin/attendance/today', [AdminAttendanceController::class, 'today']);
    Route::get('/admin/profiles', [AdminProfileController::class, 'index']);

    // Office management
    Route::post('/offices', [OfficeController::class, 'store']);
    Route::put('/offices/{id}', [OfficeController::class, 'update']);
    Route::patch('/offices/{id}', [OfficeController::class, 'toggleActive']);

    // Face verification
    Route::post('/face/verify', [FaceVerificationController::class, 'verify']);
});

