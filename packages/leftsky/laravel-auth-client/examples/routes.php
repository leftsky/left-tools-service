<?php

use Illuminate\Support\Facades\Route;
use YourApp\Http\Controllers\ApiController;

// 基本认证路由
Route::middleware('auth.api')
    ->get('/profile', [ApiController::class, 'profile']);
    
// 需要特定作用域的路由
Route::middleware(['auth.api', 'scope:admin'])
    ->get('/admin', [ApiController::class, 'admin']);
    
// 需要任意一个作用域的路由
Route::middleware(['auth.api-scope:read,write'])
    ->get('/data', function () {
        return response()->json([
            'message' => '您有读取或写入权限',
            'data' => ['item1', 'item2', 'item3']
        ]);
    }); 