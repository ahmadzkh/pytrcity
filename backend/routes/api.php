<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PpobController; // Tambahkan baris ini

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/midtrans/callback', [PpobController::class, 'paymentCallback']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // Titik Akhir PPOB Terproteksi
    Route::post('/ppob/inquiry', [PpobController::class, 'inquiry']);
    Route::post('/ppob/payment', [PpobController::class, 'createPayment']);
});
