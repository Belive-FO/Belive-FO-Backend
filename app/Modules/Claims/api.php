<?php

use App\Modules\Claims\Controllers\ClaimApprovalController;
use App\Modules\Claims\Controllers\ClaimAttachmentController;
use App\Modules\Claims\Controllers\ClaimController;
use App\Modules\Claims\Controllers\ClaimStatsController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    // Static routes first (before /claims/{claim})
    Route::get('/claims/stats', [ClaimStatsController::class, 'stats']);
    Route::get('/claims/monthly-spend', [ClaimStatsController::class, 'monthlySpend']);
    Route::get('/claims/mileage-rate', [ClaimStatsController::class, 'mileageRate']);
    Route::post('/claims/calculate-distance', [ClaimStatsController::class, 'calculateDistance']);
    Route::get('/claims/all', [ClaimApprovalController::class, 'all'])
        ->middleware('role:manager|hr_admin|super_admin');

    Route::get('/claim-categories', [ClaimStatsController::class, 'categories']);

    // Claims CRUD
    Route::get('/claims', [ClaimController::class, 'index']);
    Route::post('/claims', [ClaimController::class, 'store']);
    Route::get('/claims/{claim}', [ClaimController::class, 'show']);
    Route::put('/claims/{claim}', [ClaimController::class, 'update']);
    Route::delete('/claims/{claim}', [ClaimController::class, 'destroy']);

    // Attachments
    Route::post('/claims/{claim}/attachments', [ClaimAttachmentController::class, 'store']);
    Route::delete('/claims/{claim}/attachments/{attachment}', [ClaimAttachmentController::class, 'destroy']);

    // Approval (HR/Manager)
    Route::patch('/claims/{claim}/approve', [ClaimApprovalController::class, 'approve'])
        ->middleware('role:manager|hr_admin|super_admin');
    Route::patch('/claims/{claim}/reject', [ClaimApprovalController::class, 'reject'])
        ->middleware('role:manager|hr_admin|super_admin');
    Route::patch('/claims/{claim}/mark-paid', [ClaimApprovalController::class, 'markPaid'])
        ->middleware('role:hr_admin|super_admin');
});
