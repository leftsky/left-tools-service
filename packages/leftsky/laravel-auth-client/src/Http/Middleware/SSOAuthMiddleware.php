<?php

namespace Leftsky\AuthClient\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Leftsky\AuthClient\Facades\SSO;

class SSOAuthMiddleware
{
    /**
     * 处理SSO认证
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // 如果用户已经通过Laravel认证，直接继续
        if (Auth::check()) {
            return $next($request);
        }
        
        // 检查SSO会话
        if (session()->has('sso_user') && session()->has('sso_access_token')) {
            // 获取SSO用户数据
            $userData = session('sso_user');
            
            // 用户模型和标识字段
            $userModel = config('auth-client.sso.user_model', '\App\Models\User');
            $identifierField = config('auth-client.sso.user_find_by', 'email');
            
            // 查找用户
            if (isset($userData[$identifierField])) {
                $user = app($userModel)->where($identifierField, $userData[$identifierField])->first();
                
                // 如果用户不存在且配置允许创建用户
                if (!$user && config('auth-client.sso.create_missing_users', true)) {
                    try {
                        // 创建新用户
                        $user = app($userModel);
                        
                        // 设置基本字段
                        if (isset($userData['name'])) {
                            $user->name = $userData['name'];
                        }
                        
                        if (isset($userData['email'])) {
                            $user->email = $userData['email'];
                            
                            // 设置为已验证邮箱（如果模型有此字段）
                            if (method_exists($user, 'markEmailAsVerified')) {
                                $user->email_verified_at = now();
                            }
                        }
                        
                        // 设置手机号
                        if (isset($userData['phone_number'])) {
                            $user->phone_number = $userData['phone_number'];
                        }
                        
                        // 设置随机密码
                        $user->password = bcrypt(Str::random(16));
                        
                        // 保存用户
                        $user->save();
                        
                        Log::info('SSO中间件自动创建用户', [
                            'id' => $user->id,
                            'email' => $user->email ?? '',
                            'phone_number' => $user->phone_number ?? '',
                        ]);
                    } catch (\Exception $e) {
                        Log::error('SSO中间件自动创建用户失败', [
                            'message' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                            'data' => $userData,
                        ]);
                    }
                }
                
                // 如果找到用户，登录
                if ($user) {
                    Auth::login($user);
                    
                    // 记录日志
                    Log::info('SSO用户自动登录', [
                        'user_id' => $user->id,
                        'email' => $user->email ?? '',
                        'phone_number' => $user->phone_number ?? '',
                    ]);
                }
            }
        }
        
        return $next($request);
    }
} 