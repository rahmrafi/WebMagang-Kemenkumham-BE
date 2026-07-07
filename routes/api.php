<?php

use App\Http\Controllers\Api\Admin\AuthController;
use App\Http\Controllers\Api\Admin\PositionController as AdminPositionController;
use App\Http\Controllers\Api\Admin\SubmissionController as AdminSubmissionController;
use App\Http\Controllers\Api\PositionController;
use App\Http\Controllers\Api\SubmissionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rute Publik (Tanpa Autentikasi)
|--------------------------------------------------------------------------
*/

Route::get('/positions', [PositionController::class, 'index']);

Route::post('/submit', [SubmissionController::class, 'store'])
    // Rate limit: maksimal 5 submit per menit per IP
    ->middleware(['throttle:5,1', 'verify.turnstile']);

/*
|--------------------------------------------------------------------------
| Rute Admin (Wajib Login via Sanctum)
|--------------------------------------------------------------------------
*/

Route::post('/admin/login', [AuthController::class, 'login'])
    ->middleware('throttle:10,1');

Route::middleware('auth.admin')->prefix('admin')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/submissions', [AdminSubmissionController::class, 'index']);
    Route::patch('/submissions/{submission}/status', [AdminSubmissionController::class, 'updateStatus']);
    Route::get('/submissions/{submission}/download', [AdminSubmissionController::class, 'download']);

    Route::get('/positions', [AdminPositionController::class, 'index']);
    Route::post('/positions', [AdminPositionController::class, 'store']);
    Route::patch('/positions/{position}', [AdminPositionController::class, 'update']);
    Route::delete('/positions/{position}', [AdminPositionController::class, 'destroy']);
});
