<?php

use App\Http\Controllers\Api\V1\ApprovalWorkflowController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\DepartmentController;
use App\Http\Controllers\Api\V1\PurchaseRequestController;
use App\Http\Controllers\Api\V1\QuoteAnalysisController;
use App\Http\Controllers\Api\V1\QuoteComparisonController;
use App\Http\Controllers\Api\V1\QuoteController;
use App\Http\Controllers\Api\V1\VendorController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::post('/auth/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);

        Route::post(
            '/purchase-requests/{purchaseRequest}/submit',
            [PurchaseRequestController::class, 'submit']
        )->name('purchase-requests.submit');

        Route::get(
            '/purchase-requests/{purchaseRequest}/quotes',
            [QuoteController::class, 'indexForPurchaseRequest']
        )->name('purchase-requests.quotes.index');

        Route::post(
            '/purchase-requests/{purchaseRequest}/quotes',
            [QuoteController::class, 'storeForPurchaseRequest']
        )->name('purchase-requests.quotes.store');

        Route::post(
            '/quotes/{quote}/analyze',
            [QuoteAnalysisController::class, 'analyze']
        )->name('quotes.analyze');

        Route::get(
            '/quotes/{quote}/analysis',
            [QuoteAnalysisController::class, 'show']
        )->name('quotes.analysis.show');

        Route::get(
            '/purchase-requests/{purchaseRequest}/comparison',
            [QuoteComparisonController::class, 'show']
        )->name('purchase-requests.comparison.show');

        Route::post(
            '/purchase-requests/{purchaseRequest}/send-for-approval',
            [ApprovalWorkflowController::class, 'sendForApproval']
        )->name('purchase-requests.send-for-approval');

        Route::post(
            '/approval-steps/{approvalStep}/approve',
            [ApprovalWorkflowController::class, 'approve']
        )->name('approval-steps.approve');

        Route::post(
            '/approval-steps/{approvalStep}/reject',
            [ApprovalWorkflowController::class, 'reject']
        )->name('approval-steps.reject');

        Route::apiResource('departments', DepartmentController::class);
        Route::apiResource('vendors', VendorController::class);
        Route::apiResource('purchase-requests', PurchaseRequestController::class);
        Route::apiResource('quotes', QuoteController::class)->only(['show', 'update', 'destroy']);
    });
});
