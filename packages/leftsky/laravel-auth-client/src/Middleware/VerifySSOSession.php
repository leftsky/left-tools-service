<?php

namespace Leftsky\AuthClient\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Leftsky\AuthClient\Facades\SSO;

class VerifySSOSession
{
    /**
     * 处理传入的请求，验证SSO会话
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string|null  $guard
     * @return mixed
     */
    public function handle(Request $request, Closure $next, $guard = null)
    {
        // 检查是否有SSO会话
        if (!session('sso_access_token')) {
            // 没有SSO会话，重定向到SSO登录
            return $this->redirectToSSO($request);
        }
        
        // 验证SSO会话是否有效
        if (!SSO::verifySession()) {
            // 会话无效，清除会话数据并重定向到SSO登录
            SSO::logout();
            return $this->redirectToSSO($request);
        }
        
        // 如果启用了本地用户同步，尝试登录本地用户
        if (config('auth-client.sso.sync_local_user', false)) {
            $this->syncLocalUser($request);
        }
        
        return $next($request);
    }
    
    /**
     * 重定向到SSO登录
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    protected function redirectToSSO(Request $request)
    {
        // 保存当前URL以便登录后返回
        $returnUrl = $request->fullUrl();
        $loginUrl = SSO::getLoginUrl($returnUrl);
        
        return redirect()->away($loginUrl);
    }
    
    /**
     * 同步本地用户
     *
     * @param  \Illuminate\Http\Request  $request
     * @return void
     */
    protected function syncLocalUser(Request $request)
    {
        $ssoUser = session('sso_user');
        if (!$ssoUser) {
            return;
        }
        
        // 这里使用的是一个助手类，根据实际项目需要实现
        $userSyncer = app('sso.user_syncer');
        $localUser = $userSyncer->findOrCreateUser($ssoUser);
        
        if ($localUser) {
            Auth::guard($guard)->login($localUser);
        }
    }
}
