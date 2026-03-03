<?php

use App\Modules\Attendance\Controllers\AdminAttendanceController;
use App\Modules\Attendance\Controllers\AdminProfileController;
use App\Modules\Attendance\Controllers\AttendanceController;
use App\Modules\Attendance\Controllers\FaceVerificationController;
use App\Modules\Attendance\Controllers\OfficeController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    // Office CRUD — Superadmin only (spec: all 4 endpoints require superadmin)
    Route::middleware('role:super_admin')->group(function () {
        Route::get('/offices', [OfficeController::class, 'index']);
        Route::post('/offices', [OfficeController::class, 'store']);
        Route::put('/offices/{office}', [OfficeController::class, 'update']);
        Route::patch('/offices/{office}', [OfficeController::class, 'toggleActive']);
    });

    // Employee self‑service
    Route::get('/attendance/my-today', [AttendanceController::class, 'myToday']);
    Route::post('/attendance/logs', [AttendanceController::class, 'store']);

    // Admin analytics + staff data
    Route::get('/admin/attendance/today', [AdminAttendanceController::class, 'today']);
    Route::get('/admin/profiles', [AdminProfileController::class, 'index']);

    // Face verification
    Route::post('/face/verify', [FaceVerificationController::class, 'verify']);
});

