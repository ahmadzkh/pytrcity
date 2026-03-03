<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Middleware\IsAdmin;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\PpobController;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/midtrans/callback', [PpobController::class, 'paymentCallback']);

Route::middleware(['auth:sanctum', IsAdmin::class])->group(function () {
    Route::get('/admin/dashboard', [AdminController::class, 'getDashboardStats']);
    Route::get('/admin/transactions', [AdminController::class, 'getAllTransactions']);
    Route::get('/admin/users', [AdminController::class, 'getUsers']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // Titik Akhir PPOB Terproteksi
    Route::post('/ppob/inquiry', [PpobController::class, 'inquiry']);
    Route::post('/ppob/payment', [PpobController::class, 'createPayment']);
    Route::get('/ppob/history', [PpobController::class, 'getTransactionHistory']);
});
