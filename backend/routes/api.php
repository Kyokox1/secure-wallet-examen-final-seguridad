<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\TransferController;
use App\Http\Controllers\Api\WalletController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    // Públicos, con rate limiting (RS-08)
    Route::middleware('throttle:register')->post('/auth/register', [AuthController::class, 'register']);
    Route::middleware('throttle:login')->post('/auth/login', [AuthController::class, 'login']);
    Route::middleware('throttle:login')->post('/auth/mfa/verify', [AuthController::class, 'mfaVerify']);
    Route::post('/auth/refresh', [AuthController::class, 'refresh']);

    // Autenticados (USER o ADMIN)
    Route::middleware('jwt.auth')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::post('/auth/mfa/enable', [AuthController::class, 'mfaEnable']);
        Route::post('/auth/mfa/confirm', [AuthController::class, 'mfaConfirm']);

        Route::get('/me', [WalletController::class, 'me']);
        Route::get('/wallet', [WalletController::class, 'show']);
        Route::post('/wallet/topup', [WalletController::class, 'topup']);
        Route::get('/transactions', [WalletController::class, 'history']);

        Route::middleware('throttle:transfers')->post('/transfers', [TransferController::class, 'store']);
        Route::post('/transfers/{uuid}/confirm', [TransferController::class, 'confirm']);

        // Solo ADMIN (RS-02, verificado en servidor con RoleMiddleware)
        Route::middleware('role:ADMIN')->group(function () {
            Route::get('/admin/users', [AdminController::class, 'users']);
            Route::patch('/admin/users/{uuid}/block', [AdminController::class, 'block']);
            Route::get('/admin/audit-logs', [AdminController::class, 'auditLogs']);
        });
    });
});
