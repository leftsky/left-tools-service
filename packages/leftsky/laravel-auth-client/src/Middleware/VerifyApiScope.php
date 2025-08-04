<?php

namespace Leftsky\AuthClient\Middleware;

use Closure;
use Illuminate\Auth\AuthenticationException;
use Leftsky\AuthClient\Facades\Token;

class VerifyApiScope
{
    /**
     * 处理传入的请求，验证至少有一个所需作用域
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  ...$scopes
     * @return mixed
     *
     * @throws \Illuminate\Auth\AuthenticationException
     */
    public function handle($request, Closure $next, ...$scopes)
    {
        $token = $request->bearerToken();
        
        if (!$token) {
            return $this->unauthenticated('未提供认证令牌');
        }
        
        // 验证Token
        $result = Token::verify($token);
        
        if (!$result || !($result['valid'] ?? false)) {
            return $this->unauthenticated('无效的认证令牌');
        }
        
        // 检查是否拥有至少一个作用域
        if (!empty($scopes) && !$this->hasAnyScope($result['scopes'] ?? [], $scopes)) {
            return $this->unauthorized('权限不足：需要以下作用域之一 ' . implode(',', $scopes));
        }
        
        // 将用户信息附加到请求
        $request->merge(['auth_user' => $result['user'] ?? []]);
        $request->setUserResolver(function () use ($result) {
            return (object)($result['user'] ?? []);
        });
        
        return $next($request);
    }
    
    /**
     * 检查令牌是否至少包含一个所需的作用域
     *
     * @param array $tokenScopes
     * @param array $requiredScopes
     * @return bool
     */
    protected function hasAnyScope($tokenScopes, $requiredScopes)
    {
        foreach ($requiredScopes as $scope) {
            if (in_array($scope, $tokenScopes)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * 返回未认证响应
     *
     * @param string $message
     * @return \Illuminate\Http\Response
     */
    protected function unauthenticated($message = '未认证')
    {
        if (request()->expectsJson()) {
            return response()->json([
                'error' => $message,
                'status_code' => 401
            ], 401);
        }
        
        throw new AuthenticationException($message);
    }
    
    /**
     * 返回未授权响应
     *
     * @param string $message
     * @return \Illuminate\Http\Response
     */
    protected function unauthorized($message = '未授权')
    {
        if (request()->expectsJson()) {
            return response()->json([
                'error' => $message,
                'status_code' => 403
            ], 403);
        }
        
        abort(403, $message);
    }
} 