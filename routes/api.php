<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\MediaController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\RealtimeController;
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

Route::post('/auth/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    Route::get('/messages', [MessageController::class, 'index']);
    Route::post('/messages', [MessageController::class, 'store']);
    Route::post('/messages/read', [MessageController::class, 'markManyAsRead']);
    Route::patch('/messages/{message}/read', [MessageController::class, 'markAsRead']);

    Route::post('/media', [MediaController::class, 'store']);
    Route::post('/profile/avatar', [ProfileController::class, 'updateAvatar']);
    Route::post('/realtime/typing', [RealtimeController::class, 'updateTyping']);
    Route::post('/realtime/draft', [RealtimeController::class, 'updateDraft']);
});
