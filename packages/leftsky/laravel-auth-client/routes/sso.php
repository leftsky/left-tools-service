<?php

use Illuminate\Support\Facades\Route;
use Leftsky\AuthClient\Http\Controllers\SSOController;

Route::middleware('web')->group(function () {
    // SSO回调路由
    Route::get('/auth/sso/callback', [SSOController::class, 'callback'])
        ->name('auth.sso.callback');
        
    // SSO登出路由
    Route::get('/auth/sso/logout', [SSOController::class, 'logout'])
        ->name('auth.sso.logout');
});
