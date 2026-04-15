<?php

use App\Modules\Claims\Controllers\ClaimApprovalController;
use App\Modules\Claims\Controllers\ClaimAttachmentController;
use App\Modules\Claims\Controllers\ClaimController;
use App\Modules\Claims\Controllers\ClaimStatsController;
use App\Modules\Claims\Controllers\ClaimTypeController;
use App\Modules\Claims\Controllers\SubclaimTypeController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'check.account_status', 'check.account_locked'])->group(function () {
    // Claim types: read for all, CRUD for admin only
    Route::get('/claim-types', [ClaimTypeController::class, 'index']);
    Route::get('/claim-types/{claimType}/subclaim-types', [ClaimTypeController::class, 'subclaimTypes']);
    Route::middleware('role:hr_admin|top_management')->group(function () {
        Route::post('/claim-types', [ClaimTypeController::class, 'store']);
        Route::get('/claim-types/{claimType}', [ClaimTypeController::class, 'show']);
        Route::put('/claim-types/{claimType}', [ClaimTypeController::class, 'update']);
        Route::delete('/claim-types/{claimType}', [ClaimTypeController::class, 'destroy']);
        Route::post('/claim-types/{claimType}/subclaim-types', [SubclaimTypeController::class, 'store']);
        Route::get('/claim-types/{claimType}/subclaim-types/{subclaimType}', [SubclaimTypeController::class, 'show']);
        Route::put('/claim-types/{claimType}/subclaim-types/{subclaimType}', [SubclaimTypeController::class, 'update']);
        Route::delete('/claim-types/{claimType}/subclaim-types/{subclaimType}', [SubclaimTypeController::class, 'destroy']);
    });

    // Static routes first (before /claims/{claim})
    Route::get('/claims/stats', [ClaimStatsController::class, 'stats']);
    Route::get('/claims/monthly-spend', [ClaimStatsController::class, 'monthlySpend']);
    Route::get('/claims/mileage-rate', [ClaimStatsController::class, 'mileageRate']);
    Route::post('/claims/calculate-distance', [ClaimStatsController::class, 'calculateDistance']);
    Route::get('/claims/all', [ClaimApprovalController::class, 'all'])
        ->middleware('role:hod|hr_admin|top_management');

    Route::get('/claim-categories', [ClaimStatsController::class, 'categories']);

    // Claims CRUD
    Route::get('/claims', [ClaimController::class, 'index']);
    Route::post('/claims', [ClaimController::class, 'store']);
    Route::get('/claims/{claim}/approvals', [ClaimApprovalController::class, 'approvals']);
    Route::get('/claims/{claim}', [ClaimController::class, 'show']);
    Route::put('/claims/{claim}', [ClaimController::class, 'update']);
    Route::patch('/claims/{claim}/submit', [ClaimController::class, 'submit']);
    Route::delete('/claims/{claim}', [ClaimController::class, 'destroy']);

    // Attachments
    Route::post('/claims/{claim}/attachments', [ClaimAttachmentController::class, 'store']);
    Route::delete('/claims/{claim}/attachments/{attachment}', [ClaimAttachmentController::class, 'destroy']);

    // Approval (HR/Manager)
    Route::patch('/claims/{claim}/approve', [ClaimApprovalController::class, 'approve'])
        ->middleware('role:hod|hr_admin|top_management');
    Route::post('/claims/{claim}/reject', [ClaimApprovalController::class, 'reject'])
        ->middleware('role:hod|hr_admin|top_management');
    Route::patch('/claims/{claim}/mark-paid', [ClaimApprovalController::class, 'markPaid'])
        ->middleware('role:hr_admin|top_management');
});
