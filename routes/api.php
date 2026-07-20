<?php

use App\Http\Controllers\Api\Admin\AuthController;
use App\Http\Controllers\Api\Admin\CertificateController;
use App\Http\Controllers\Api\Admin\DocumentController;
use App\Http\Controllers\Api\Admin\PeriodController as AdminPeriodController;
use App\Http\Controllers\Api\Admin\SubmissionController as AdminSubmissionController;
use App\Http\Controllers\Api\PeriodController;
use App\Http\Controllers\Api\SubmissionController;
use App\Http\Middleware\EnsureDesktopOrTablet;
use App\Http\Middleware\EnsureUserIsAdmin;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rute Publik (Tanpa Autentikasi)
|--------------------------------------------------------------------------
*/

Route::get('/periods', [PeriodController::class, 'index']);
Route::get('/check-status', [SubmissionController::class, 'checkStatus']);
Route::get('/submissions/{submission}/messages', [SubmissionController::class, 'messages']);
Route::post('/submissions/{submission}/messages', [SubmissionController::class, 'sendMessage']);
Route::get('/submissions/{submission}/permit/download', [SubmissionController::class, 'downloadPermit']);

Route::post('/submit', [SubmissionController::class, 'store'])
    // Rate limit: maksimal 5 submit per menit per IP
    ->middleware(['throttle:5,1', 'verify.turnstile']);

/*
|--------------------------------------------------------------------------
| Rute Admin (Wajib Login via Sanctum)
|--------------------------------------------------------------------------
*/

Route::post('/admin/login', [AuthController::class, 'login'])
    ->middleware(['throttle:10,1', EnsureDesktopOrTablet::class]);

Route::middleware(['auth:sanctum', EnsureUserIsAdmin::class, EnsureDesktopOrTablet::class])->prefix('admin')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::get('/submissions/export', [AdminSubmissionController::class, 'export']);
    Route::get('/submissions', [AdminSubmissionController::class, 'index']);
    Route::patch('/submissions/{submission}/status', [AdminSubmissionController::class, 'updateStatus']);
    Route::patch('/submissions/{submission}/dates', [AdminSubmissionController::class, 'updateDates']);
    Route::get('/submissions/{submission}/download', [AdminSubmissionController::class, 'download']);
    Route::get('/submissions/{submission}/generate-template', [DocumentController::class, 'generateTemplate']);
    Route::post('/submissions/{submission}/permit', [AdminSubmissionController::class, 'uploadPermit']);
    Route::post('/submissions/{submission}/discussion/start', [AdminSubmissionController::class, 'startDiscussion']);
    Route::get('/submissions/{submission}/messages', [AdminSubmissionController::class, 'messages']);
    Route::post('/submissions/{submission}/messages', [AdminSubmissionController::class, 'sendMessage']);

    Route::get('/periods', [AdminPeriodController::class, 'index']);
    Route::post('/periods', [AdminPeriodController::class, 'store']);
    Route::patch('/periods/{period}', [AdminPeriodController::class, 'update']);
    Route::delete('/periods/{period}', [AdminPeriodController::class, 'destroy']);

    Route::get('/settings', [App\Http\Controllers\Api\Admin\SettingsController::class, 'index']);
    Route::put('/settings', [App\Http\Controllers\Api\Admin\SettingsController::class, 'update']);

    // ── Sertifikat ──────────────────────────────────────────────────────────
    Route::get('/certificate/settings', [CertificateController::class, 'getSettings']);
    Route::get('/certificate/template/preview', [CertificateController::class, 'previewTemplate']);
    Route::post('/certificate/template', [CertificateController::class, 'uploadTemplate']);
    Route::delete('/certificate/template', [CertificateController::class, 'deleteTemplate']);
    Route::post('/certificate/fields', [CertificateController::class, 'saveFields']);
    Route::post('/submissions/{submission}/certificate', [CertificateController::class, 'generate']);
    Route::get('/submissions/{submission}/certificate/download', [CertificateController::class, 'download']);
});
