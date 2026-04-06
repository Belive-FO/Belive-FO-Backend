<?php

use App\Http\Controllers\Auth\LarkAuthController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\GeocodeController;
use App\Http\Controllers\MapsConfigController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReverseGeocodeController;
use Illuminate\Support\Facades\Route;

// Public auth routes
Route::post('/auth/lark/callback', [LarkAuthController::class, 'callback'])
    ->middleware('throttle:30,1');
Route::post('/auth/login', [LarkAuthController::class, 'login'])
    ->middleware('throttle:5,1');

// Protected auth routes (require authentication)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [LarkAuthController::class, 'logout'])
        ->middleware('throttle:10,1');

    Route::post('/auth/resubmit', [LarkAuthController::class, 'resubmit'])
        ->middleware('throttle:3,5');

    Route::get('/user', [LarkAuthController::class, 'me']);

    Route::middleware(['check.account_status', 'check.account_locked'])->group(function () {
        // Profile
        Route::get('/profile/me', [ProfileController::class, 'me']);
        Route::put('/profile/me', [ProfileController::class, 'updateMe']);
        Route::post('/profile/face-photo', [ProfileController::class, 'uploadFacePhoto']);

        // Geocode (Google Geocoding API, server-side)
        Route::post('/geocode', [GeocodeController::class, 'geocode']);
        Route::post('/reverse-geocode', [ReverseGeocodeController::class, 'reverseGeocode']);
        Route::get('/maps-config', [MapsConfigController::class, 'index']);

        // Departments (super admin)
        Route::middleware('check.super_admin')->group(function () {
            Route::get('/departments', [DepartmentController::class, 'index']);
            Route::post('/departments', [DepartmentController::class, 'store']);
            Route::get('/departments/{department}', [DepartmentController::class, 'show']);
            Route::put('/departments/{department}', [DepartmentController::class, 'update']);
            Route::delete('/departments/{department}', [DepartmentController::class, 'destroy']);
        });

        // Onboarding (super admin only)
        Route::middleware('check.super_admin')->group(function () {
            Route::get('/onboarding/approval-roles', [OnboardingController::class, 'approvalRoles']);
            Route::get('/onboarding', [OnboardingController::class, 'index']);
            Route::post('/onboarding/{userOnboarding}/approval', [OnboardingController::class, 'approval']);
            Route::post('/onboarding/{userOnboarding}/rejection', [OnboardingController::class, 'rejection']);
        });
    });
});
