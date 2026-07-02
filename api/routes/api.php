<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\WorkController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Admin\AdminWorkController;

// 公開API
Route::get('/works', [WorkController::class, 'index']);
Route::post('/contact', [ContactController::class, 'send']);
Route::post('/admin/login', [AuthController::class, 'login']);

// 管理API(Bearerトークン必須)
Route::middleware('admin.token')->prefix('admin')->group(function () {
    Route::post('/works', [AdminWorkController::class, 'store']);
    Route::post('/works/{work}', [AdminWorkController::class, 'update']); // 画像付きのためPOST
    Route::delete('/works/{work}', [AdminWorkController::class, 'destroy']);
    Route::post('/works-reorder', [AdminWorkController::class, 'reorder']);
});
