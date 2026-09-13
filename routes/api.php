<?php

use App\Http\Controllers\Api\V1\Admin\EventController as AdminEventController;
use App\Http\Controllers\Api\V1\Admin\MediaController as AdminMediaController;
use App\Http\Controllers\Api\V1\Admin\PasswordController;
use App\Http\Controllers\Api\V1\Admin\PostController as AdminPostController;
use App\Http\Controllers\Api\V1\Admin\ProfileController;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Public\EventController as PublicEventController;
use App\Http\Controllers\Api\V1\Public\PostController as PublicPostController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('login', [AuthController::class, 'login'])->middleware('throttle:5,1');

        Route::middleware('auth:sanctum')->group(function () {
            Route::post('logout', [AuthController::class, 'logout']);
            Route::get('me', [AuthController::class, 'me']);
        });
    });

    Route::middleware('auth:sanctum')->prefix('admin')->group(function () {
        Route::get('profile', [ProfileController::class, 'show']);
        Route::put('profile', [ProfileController::class, 'update']);
        Route::put('password', [PasswordController::class, 'update']);

        Route::get('events', [AdminEventController::class, 'index']);
        Route::post('events', [AdminEventController::class, 'store']);
        Route::get('events/{event}', [AdminEventController::class, 'show']);
        Route::put('events/{event}', [AdminEventController::class, 'update']);
        Route::delete('events/{event}', [AdminEventController::class, 'destroy']);

        Route::get('posts', [AdminPostController::class, 'index']);
        Route::post('posts', [AdminPostController::class, 'store']);
        Route::get('posts/{post}', [AdminPostController::class, 'show']);
        Route::put('posts/{post}', [AdminPostController::class, 'update']);
        Route::delete('posts/{post}', [AdminPostController::class, 'destroy']);

        Route::get('media', [AdminMediaController::class, 'index']);
        Route::post('media', [AdminMediaController::class, 'store']);
        Route::delete('media/{media}', [AdminMediaController::class, 'destroy']);
    });

    Route::prefix('events')->group(function () {
        Route::get('/', [PublicEventController::class, 'index']);
        Route::get('{slug}', [PublicEventController::class, 'show']);
    });

    Route::prefix('posts')->group(function () {
        Route::get('/', [PublicPostController::class, 'index']);
        Route::get('{slug}', [PublicPostController::class, 'show']);
    });
});
