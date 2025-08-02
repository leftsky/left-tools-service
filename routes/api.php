<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ToolController;
use App\Http\Controllers\Api\AccessLogController;
use App\Http\Controllers\SeoController;

// 小程序登录接口
Route::post('/auth/mini-login', [AuthController::class, 'miniLogin']);

// H5登录接口
Route::post('/auth/h5-login', [AuthController::class, 'h5Login']);

// 访问日志接口（无需认证）
Route::middleware(['throttle:60,1'])->group(function () {
    Route::post('/access-log', [AccessLogController::class, 'store']);
    Route::any('/access-log/error', [AccessLogController::class, 'logError']);
    Route::get('/access-log/stats', [AccessLogController::class, 'stats']);
});

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

// 工具相关接口
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/tools/record-usage', [ToolController::class, 'recordUsage']);
});

// 可选认证的工具接口（支持登录和未登录用户）
Route::middleware('optional.auth')->group(function () {
    Route::post('/tools/extract-douyin', [ToolController::class, 'extractDouyin']);
    Route::post('/tools/parse-douyin', [ToolController::class, 'parseDouyin']);
});

// 无需认证的工具接口
Route::post('/tools/record-usage-public', [ToolController::class, 'recordUsagePublic']);

// SEO相关接口
Route::get('/seo/page-info', [SeoController::class, 'getPageSeo']);
Route::get('/seo/structured-data', [SeoController::class, 'getStructuredData']);
