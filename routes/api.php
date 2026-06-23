<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\DepartmentController;
use App\Http\Controllers\Api\V1\PurchaseRequestController;
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

        Route::apiResource('departments', DepartmentController::class);
        Route::apiResource('vendors', VendorController::class);
        Route::apiResource('purchase-requests', PurchaseRequestController::class);
    });
});
