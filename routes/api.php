<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CarController;
use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\DriverController;
use App\Http\Controllers\Api\V1\PlatformTenantController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\ZoneController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::post('auth/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('auth/me', [AuthController::class, 'me']);
    });

    Route::middleware(['auth:sanctum', 'platform.admin'])->prefix('platform')->group(function (): void {
        Route::apiResource('tenants', PlatformTenantController::class);
    });

    Route::middleware(['auth:sanctum', 'tenant', 'role:admin,manager'])->group(function (): void {
        Route::apiResource('zones', ZoneController::class);
        Route::apiResource('cars', CarController::class);
        Route::apiResource('drivers', DriverController::class);
        Route::apiResource('products', ProductController::class);
        Route::apiResource('customers', CustomerController::class);

        Route::get('users', [UserController::class, 'index']);
        Route::get('users/{user}', [UserController::class, 'show']);
    });

    Route::middleware(['auth:sanctum', 'tenant', 'role:admin'])->group(function (): void {
        Route::post('users', [UserController::class, 'store']);
        Route::put('users/{user}', [UserController::class, 'update']);
        Route::patch('users/{user}', [UserController::class, 'update']);
        Route::delete('users/{user}', [UserController::class, 'destroy']);
    });
});
