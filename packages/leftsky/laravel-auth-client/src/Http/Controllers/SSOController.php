<?php

namespace Leftsky\AuthClient\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Leftsky\AuthClient\Facades\SSO;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SSOController extends Controller
{
    /**
     * 处理SSO回调
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function callback(Request $request)
    {
        // 验证参数
        $request->validate([
            'code' => 'required',
            'state' => 'required',
        ]);
        
        // 防止无限循环
        if (session()->has('sso_callback_count')) {
            $count = session('sso_callback_count') + 1;
            if ($count > 3) {
                session()->forget('sso_callback_count');
                return redirect('/')->with('error', '登录过程中发生错误，请稍后再试');
            }
            session(['sso_callback_count' => $count]);
        } else {
            session(['sso_callback_count' => 1]);
        }
        
        // 处理回调 - 传递整个Request对象
        $result = SSO::handleCallback($request);
        
        if (!$result) {
            return redirect('/')->with('error', '单点登录验证失败');
        }
        
        // 存储会话数据
        session([
            'sso_access_token' => $result['token']['access_token'],
            'sso_refresh_token' => $result['token']['refresh_token'] ?? null,
            'sso_user' => $result['user'],
        ]);
        
        // 同步到 Laravel 认证系统
        if (config('auth-client.sso.sync_local_user', false)) {
            $userData = $result['user'];
            $userModel = config('auth-client.sso.user_model', '\App\Models\User');
            $identifierField = config('auth-client.sso.user_find_by', 'email');
            
            // 查找用户
            $user = app($userModel)->where($identifierField, $userData[$identifierField] ?? '')->first();
            
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
                    
                    // 设置随机密码（用户不会用这个密码登录，但需要填充字段）
                    $user->password = bcrypt(Str::random(16));
                    
                    // 保存用户
                    $user->save();
                    
                    Log::info('SSO自动创建用户', [
                        'id' => $user->id,
                        'email' => $user->email ?? '',
                        'phone_number' => $user->phone_number ?? '',
                    ]);
                } catch (\Exception $e) {
                    Log::error('SSO自动创建用户失败', [
                        'message' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                        'data' => $userData,
                    ]);
                }
            }
            
            // 如果用户存在，登录该用户
            if ($user) {
                auth()->login($user);
            }
        }
        
        // 触发登录事件
        if (config('auth-client.sso.dispatch_login_event', true)) {
            event(new \Leftsky\AuthClient\Events\SSOLogin($result['user']));
        }
        
        // 清除计数器
        session()->forget('sso_callback_count');
        
        // 重定向回原始URL
        return redirect($result['return_url']);
    }
    
    /**
     * 处理SSO登出
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function logout(Request $request)
    {
        // 清除本地会话
        SSO::logout();
        
        // 如果启用了本地用户同步，同时登出本地用户
        if (config('auth-client.sso.sync_local_user', false)) {
            auth()->logout();
        }
        
        // 获取重定向URL
        $returnUrl = $request->input('redirect_uri', url('/'));
        
        // 如果配置为集中式登出，重定向到SSO登出页面
        if (config('auth-client.sso.centralized_logout', true)) {
            return redirect()->away(SSO::getLogoutUrl($returnUrl));
        }
        
        // 否则直接重定向回原始URL
        return redirect($returnUrl);
    }
} 