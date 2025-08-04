<?php

use Illuminate\Support\Facades\Route;
use Leftsky\AuthClient\Http\Controllers\SSOController;

// 添加SSO回调路由
Route::middleware('web')->group(function () {
    Route::get('/auth/callback', [SSOController::class, 'callback'])
        ->name('auth.callback');
}); 