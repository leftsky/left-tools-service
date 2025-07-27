<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Traits\ApiResponseTrait;
use EasyWeChat\MiniApp\Application;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    use ApiResponseTrait;
    /**
     * 小程序登录
     */
    public function miniLogin(Request $request): JsonResponse
    {
        try {
            // 验证请求参数
            $request->validate([
                'code' => 'required|string',
            ]);

            $code = $request->input('code');

            // 获取微信小程序配置
            $config = config('app.wechat.mini_program');

            if (!$config['app_id'] || !$config['secret']) {
                Log::error('微信小程序配置缺失', [
                    'app_id' => $config['app_id'],
                    'app_secret' => $config['secret'] ? '已设置' : '未设置'
                ]);

                return $this->serverError('服务配置错误');
            }

            // 使用EasyWeChat创建小程序应用实例
            $app = new Application($config);

            // 使用code换取openid和session_key
            $result = $app->getUtils()->codeToSession($code);

            // 检查EasyWeChat返回结果
            if (isset($result['errcode']) && $result['errcode'] !== 0) {
                Log::error('微信接口调用失败', [
                    'errcode' => $result['errcode'],
                    'errmsg' => $result['errmsg'] ?? '未知错误',
                    'code' => $code,
                ]);

                return $this->error('微信登录失败: ' . ($result['errmsg'] ?? '未知错误'), 400);
            }

            $openid = $result['openid'] ?? null;
            $sessionKey = $result['session_key'] ?? null;
            $unionid = $result['unionid'] ?? null;

            if (!$openid) {
                Log::error('微信返回数据异常', $result);

                return $this->error('微信返回数据异常', 400);
            }

            // 查找或创建用户
            $user = User::where('weixin_mini_openid', $openid)->first();

            if (!$user) {
                // 创建新用户
                $user = User::create([
                    'name' => '微信用户_' . substr($openid, -8), // 生成默认用户名
                    'email' => $openid . '@wechat.local', // 生成临时邮箱
                    'password' => bcrypt(Str::random(16)), // 生成随机密码
                    'weixin_mini_openid' => $openid,
                    'weixin_unionid' => $unionid,
                ]);

                Log::info('创建新微信用户', [
                    'user_id' => $user->id,
                    'openid' => $openid,
                    'unionid' => $unionid,
                ]);
            } else {
                // 更新unionid（如果之前没有）
                if ($unionid && !$user->weixin_unionid) {
                    $user->update(['weixin_unionid' => $unionid]);

                    Log::info('更新用户unionid', [
                        'user_id' => $user->id,
                        'unionid' => $unionid,
                    ]);
                }
            }

            // 生成API Token
            $token = $user->createToken('mini-app-token')->plainTextToken;

            Log::info('小程序登录成功', [
                'user_id' => $user->id,
                'openid' => $openid,
            ]);

            return $this->success([
                'token' => $token,
                'user' => new UserResource($user),
            ], '登录成功');
        } catch (ValidationException $e) {
            return $this->validationError($e->errors(), '参数错误');
        } catch (\Exception $e) {
            Log::error('小程序登录异常', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->serverError('服务器内部错误');
        }
    }

    /**
     * 获取当前用户信息
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return $this->success(new UserResource($user), '获取用户信息成功');
    }

    /**
     * 退出登录
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => '退出成功',
        ]);
    }
}
