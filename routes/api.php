<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\OpenApiController;

// 小程序登录接口
Route::post('/auth/mini-login', [AuthController::class, 'miniLogin']);

// 需要认证的接口
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    
    // 获取当前用户信息
    Route::get('/auth/me', [AuthController::class, 'me']);
    
    // 退出登录
    Route::post('/auth/logout', [AuthController::class, 'logout']);
});

// API文档路由
Route::get('/docs', [OpenApiController::class, 'documentation']);
