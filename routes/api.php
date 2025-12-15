<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\AtvController;
use App\Http\Controllers\Api\RentalController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group.
|
*/

// Public routes
Route::post('/register', [AuthController::class, 'register']);
Route::get('/verify-email/{token}', [AuthController::class, 'verifyEmail']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);

// Public ATV routes (browse without login)
Route::get('/atvs', [AtvController::class, 'index']);
Route::get('/atvs/types', [AtvController::class, 'types']);
Route::get('/atvs/{id}', [AtvController::class, 'show']);

// Protected routes (requires authentication)
Route::middleware('auth:sanctum')->group(function () {
    // Auth routes
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/change-password', [AuthController::class, 'changePassword']);
    
    // Profile routes
    Route::get('/profile', [UserController::class, 'profile']);
    Route::post('/profile', [UserController::class, 'updateProfile']);
    
    // Rental routes (all authenticated users)
    Route::get('/rentals', [RentalController::class, 'index']);
    Route::post('/rentals', [RentalController::class, 'store']);
    Route::get('/rentals/{id}', [RentalController::class, 'show']);
    Route::post('/rentals/{id}/cancel', [RentalController::class, 'cancel']);
    Route::post('/rentals/{id}/request-pickup', [RentalController::class, 'requestPickup']);
    Route::post('/rentals/{id}/request-return', [RentalController::class, 'requestReturn']);
    
    // Admin/Manager routes
    Route::middleware('role:admin,manager')->group(function () {
        // ATV management
        Route::post('/atvs', [AtvController::class, 'store']);
        Route::post('/atvs/{id}', [AtvController::class, 'update']); // POST for file upload
        Route::delete('/atvs/{id}', [AtvController::class, 'destroy']);
        
        // Rental status management
        Route::put('/rentals/{id}/status', [RentalController::class, 'updateStatus']);
    });
    
    // Admin only routes
    Route::middleware('role:admin')->group(function () {
        Route::get('/users', [UserController::class, 'index']);
        Route::post('/users', [UserController::class, 'store']);
        Route::get('/users/{id}', [UserController::class, 'show']);
        Route::put('/users/{id}', [UserController::class, 'update']);
        Route::delete('/users/{id}', [UserController::class, 'destroy']);
    });
});

