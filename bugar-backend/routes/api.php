<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BodyMetricController;
use App\Http\Controllers\Api\EmailVerificationController;
use App\Http\Controllers\Api\MealPlanController;
use App\Http\Controllers\Api\ProgramController;
use App\Http\Controllers\Api\ProgressionController;
use App\Http\Controllers\Api\WorkoutController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// ---- Public (tanpa token) ----
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/email/resend', [EmailVerificationController::class, 'resend'])
    ->middleware('throttle:6,1');
Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
    ->middleware(['signed', 'throttle:6,1'])
    ->name('verification.verify');

// ---- Butuh token (auth:sanctum) ----
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // ---- Butuh token + email terverifikasi (sesuai CLAUDE.md) ----
    Route::middleware('verified')->group(function () {
        // Meal plan
        Route::post('/meal-plan/generate', [MealPlanController::class, 'generate']);

        // Program latihan adaptif
        Route::post('/program/generate', [ProgramController::class, 'generate']);

        // Workout logging
        Route::get('/workouts', [WorkoutController::class, 'index']);
        Route::post('/workouts', [WorkoutController::class, 'store']);

        // Rekomendasi progressive overload per exercise
        Route::get('/exercises/{exerciseId}/progression', [ProgressionController::class, 'show']);

        // Body metrics (BMI, body fat)
        Route::get('/body-metrics', [BodyMetricController::class, 'index']);
        Route::post('/body-metrics', [BodyMetricController::class, 'store']);
    });
});
