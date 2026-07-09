<?php

use App\Http\Controllers\Api\Admin\AuthController;
use App\Http\Controllers\Api\Admin\PeriodController as AdminPeriodController;
use App\Http\Controllers\Api\Admin\SubmissionController as AdminSubmissionController;
use App\Http\Controllers\Api\PeriodController;
use App\Http\Controllers\Api\SubmissionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rute Publik (Tanpa Autentikasi)
|--------------------------------------------------------------------------
*/

Route::get('/periods', [PeriodController::class, 'index']);

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

use App\Http\Middleware\EnsureUserIsAdmin;

Route::middleware(['auth:sanctum', EnsureUserIsAdmin::class])->prefix('admin')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/submissions', [AdminSubmissionController::class, 'index']);
    Route::patch('/submissions/{submission}/status', [AdminSubmissionController::class, 'updateStatus']);
    Route::patch('/submissions/{submission}/dates', [AdminSubmissionController::class, 'updateDates']);
    Route::get('/submissions/{submission}/download', [AdminSubmissionController::class, 'download']);

    Route::get('/periods', [AdminPeriodController::class, 'index']);
    Route::post('/periods', [AdminPeriodController::class, 'store']);
    Route::patch('/periods/{period}', [AdminPeriodController::class, 'update']);
    Route::delete('/periods/{period}', [AdminPeriodController::class, 'destroy']);
});
