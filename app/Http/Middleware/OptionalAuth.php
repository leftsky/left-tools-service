<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class OptionalAuth
{
    /**
     * Handle an incoming request.
     * 可选认证中间件：如果提供了有效的令牌就认证用户，否则继续处理请求
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 设置使用 sanctum guard
        Auth::shouldUse('sanctum');
        
        // 如果有 Bearer Token，尝试认证用户
        // Laravel 会自动处理令牌验证，如果令牌无效或过期，Auth::user() 会返回 null
        // 不会抛出异常，这正是我们想要的行为
        if ($request->bearerToken()) {
            // 尝试获取用户，如果令牌无效会返回 null，不会抛出异常
            Auth::guard('sanctum')->user();
        }

        return $next($request);
    }
}
