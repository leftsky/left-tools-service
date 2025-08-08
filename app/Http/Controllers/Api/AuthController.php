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
use OpenApi\Attributes as OA;

#[OA\Tag(
    name: '认证管理',
    description: '用户认证相关接口'
)]
class AuthController extends Controller
{
    use ApiResponseTrait;

    /**
     * 小程序登录
     */
    #[OA\Post(
        path: '/api/auth/mini-login',
        summary: '微信小程序登录',
        description: '使用微信小程序code换取用户token',
        tags: ['认证管理'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['code'],
                properties: [
                    new OA\Property(
                        property: 'code',
                        type: 'string',
                        description: '微信小程序登录code',
                        example: 'wx_code_123456'
                    )
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: '登录成功',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 1),
                        new OA\Property(property: 'status', type: 'string', example: 'success'),
                        new OA\Property(property: 'message', type: 'string', example: '登录成功'),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'token', type: 'string', example: '1|abc123...'),
                                new OA\Property(
                                    property: 'user',
                                    type: 'object',
                                    properties: [
                                        new OA\Property(property: 'id', type: 'integer', example: 1),
                                        new OA\Property(property: 'name', type: 'string', example: '微信用户_12345678'),
                                        new OA\Property(property: 'email', type: 'string', example: 'openid123@wechat.local'),
                                        new OA\Property(property: 'phone', type: 'string', nullable: true, example: null),
                                        new OA\Property(property: 'weixin_mini_openid', type: 'string', example: 'openid123'),
                                        new OA\Property(property: 'weixin_unionid', type: 'string', example: 'unionid123'),
                                        new OA\Property(property: 'email_verified_at', type: 'string', nullable: true, example: null),
                                        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
                                        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time')
                                    ]
                                )
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(
                response: 400,
                description: '请求错误',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 0),
                        new OA\Property(property: 'status', type: 'string', example: 'error'),
                        new OA\Property(property: 'message', type: 'string', example: '微信登录失败: code无效'),
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                description: '验证失败',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 0),
                        new OA\Property(property: 'status', type: 'string', example: 'error'),
                        new OA\Property(property: 'message', type: 'string', example: '参数错误'),
                        new OA\Property(
                            property: 'errors',
                            type: 'object',
                            properties: [
                                new OA\Property(
                                    property: 'code',
                                    type: 'array',
                                    items: new OA\Items(type: 'string'),
                                    example: ['code字段是必需的']
                                )
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(
                response: 500,
                description: '服务器错误',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 0),
                        new OA\Property(property: 'status', type: 'string', example: 'error'),
                        new OA\Property(property: 'message', type: 'string', example: '服务器内部错误'),
                    ]
                )
            )
        ]
    )]
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
    #[OA\Get(
        path: '/api/auth/me',
        summary: '获取当前用户信息',
        description: '获取当前登录用户的详细信息',
        tags: ['认证管理'],
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: '获取成功',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 1),
                        new OA\Property(property: 'status', type: 'string', example: 'success'),
                        new OA\Property(property: 'message', type: 'string', example: '获取用户信息成功'),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 1),
                                new OA\Property(property: 'name', type: 'string', example: '微信用户_12345678'),
                                new OA\Property(property: 'email', type: 'string', example: 'openid123@wechat.local'),
                                new OA\Property(property: 'phone', type: 'string', nullable: true, example: null),
                                new OA\Property(property: 'weixin_mini_openid', type: 'string', example: 'openid123'),
                                new OA\Property(property: 'weixin_unionid', type: 'string', example: 'unionid123'),
                                new OA\Property(property: 'email_verified_at', type: 'string', nullable: true, example: null),
                                new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
                                new OA\Property(property: 'updated_at', type: 'string', format: 'date-time')
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: '未授权',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 0),
                        new OA\Property(property: 'status', type: 'string', example: 'error'),
                        new OA\Property(property: 'message', type: 'string', example: '未授权访问'),
                    ]
                )
            )
        ]
    )]
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return $this->success(new UserResource($user), '获取用户信息成功');
    }

    /**
     * H5登录
     */
    #[OA\Post(
        path: '/api/auth/h5-login',
        summary: 'H5登录',
        description: '登录默认的H5用户，不存在则创建',
        tags: ['认证管理'],
        responses: [
            new OA\Response(
                response: 200,
                description: '登录成功',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 1),
                        new OA\Property(property: 'status', type: 'string', example: 'success'),
                        new OA\Property(property: 'message', type: 'string', example: '登录成功'),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'token', type: 'string', example: '1|abc123...'),
                                new OA\Property(
                                    property: 'user',
                                    type: 'object',
                                    properties: [
                                        new OA\Property(property: 'id', type: 'integer', example: 1),
                                        new OA\Property(property: 'name', type: 'string', example: 'H5用户'),
                                        new OA\Property(property: 'email', type: 'string', example: 'h5@leftsky.top'),
                                        new OA\Property(property: 'phone', type: 'string', nullable: true, example: null),
                                        new OA\Property(property: 'weixin_mini_openid', type: 'string', nullable: true, example: null),
                                        new OA\Property(property: 'weixin_unionid', type: 'string', nullable: true, example: null),
                                        new OA\Property(property: 'email_verified_at', type: 'string', nullable: true, example: null),
                                        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
                                        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time')
                                    ]
                                )
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(
                response: 500,
                description: '服务器错误',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 0),
                        new OA\Property(property: 'status', type: 'string', example: 'error'),
                        new OA\Property(property: 'message', type: 'string', example: '服务器内部错误'),
                    ]
                )
            )
        ]
    )]
    public function h5Login(Request $request): JsonResponse
    {
        try {
            // 查找或创建H5用户
            $user = User::where('email', 'h5@leftsky.top')->first();

            if (!$user) {
                // 创建H5用户
                $user = User::create([
                    'name' => 'H5用户',
                    'email' => 'h5@leftsky.top',
                    'password' => bcrypt(Str::random(16)), // 生成随机密码
                ]);

                Log::info('创建H5用户', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                ]);
            }

            // 生成API Token
            $token = $user->createToken('h5-token')->plainTextToken;

            Log::info('H5登录成功', [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);

            return $this->success([
                'token' => $token,
                'user' => new UserResource($user),
            ], '登录成功');

        } catch (\Exception $e) {
            Log::error('H5登录异常', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->serverError('服务器内部错误');
        }
    }

    /**
     * 退出登录
     */
    #[OA\Post(
        path: '/api/auth/logout',
        summary: '退出登录',
        description: '删除当前用户的访问令牌',
        tags: ['认证管理'],
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: '退出成功',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 1),
                        new OA\Property(property: 'status', type: 'string', example: 'success'),
                        new OA\Property(property: 'message', type: 'string', example: '退出成功'),
                        new OA\Property(property: 'data', type: 'object', example: [])
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: '未授权',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 0),
                        new OA\Property(property: 'status', type: 'string', example: 'error'),
                        new OA\Property(property: 'message', type: 'string', example: '未授权访问'),
                    ]
                )
            )
        ]
    )]
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return $this->success([], '退出成功');
    }
}
