<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'Left Tools Service API',
    description: '工具服务API文档',
    contact: new OA\Contact(
        name: 'LeftSky',
        email: 'admin@leftsky.top'
    ),
    license: new OA\License(
        name: 'MIT',
        url: 'https://opensource.org/licenses/MIT'
    )
)]
#[OA\Server(
    url: 'http://localhost:8000',
    description: '本地开发环境'
)]
#[OA\Server(
    url: 'https://api.leftsky.top',
    description: '生产环境'
)]
#[OA\SecurityScheme(
    securityScheme: 'sanctum',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'JWT'
)]
#[OA\Tag(
    name: '认证管理',
    description: '用户认证相关接口'
)]
class OpenApiController extends Controller
{
    /**
     * 获取OpenAPI文档
     */
    public function documentation()
    {
        return response()->json([
            'openapi' => '3.0.0',
            'info' => [
                'title' => 'Left Tools Service API',
                'version' => '1.0.0',
                'description' => '工具服务API文档',
                'contact' => [
                    'name' => 'LeftSky',
                    'email' => 'admin@leftsky.top'
                ],
                'license' => [
                    'name' => 'MIT',
                    'url' => 'https://opensource.org/licenses/MIT'
                ]
            ],
            'servers' => [
                [
                    'url' => 'http://localhost:8000',
                    'description' => '本地开发环境'
                ],
                [
                    'url' => 'https://api.leftsky.top',
                    'description' => '生产环境'
                ]
            ],
            'security' => [
                ['sanctum' => []]
            ],
            'paths' => [
                '/api/auth/mini-login' => [
                    'post' => [
                        'tags' => ['认证管理'],
                        'summary' => '微信小程序登录',
                        'description' => '使用微信小程序code换取用户token',
                        'requestBody' => [
                            'required' => true,
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'required' => ['code'],
                                        'properties' => [
                                            'code' => [
                                                'type' => 'string',
                                                'description' => '微信小程序登录code',
                                                'example' => 'wx_code_123456'
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ],
                        'responses' => [
                            '200' => [
                                'description' => '登录成功',
                                'content' => [
                                    'application/json' => [
                                        'schema' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'code' => ['type' => 'integer', 'example' => 1],
                                                'status' => ['type' => 'string', 'example' => 'success'],
                                                'message' => ['type' => 'string', 'example' => '登录成功'],
                                                'data' => [
                                                    'type' => 'object',
                                                    'properties' => [
                                                        'token' => ['type' => 'string', 'example' => '1|abc123...'],
                                                        'user' => [
                                                            'type' => 'object',
                                                            'properties' => [
                                                                'id' => ['type' => 'integer', 'example' => 1],
                                                                'name' => ['type' => 'string', 'example' => '微信用户_12345678'],
                                                                'email' => ['type' => 'string', 'example' => 'openid123@wechat.local'],
                                                                'phone' => ['type' => 'string', 'nullable' => true, 'example' => null],
                                                                'weixin_mini_openid' => ['type' => 'string', 'example' => 'openid123'],
                                                                'weixin_unionid' => ['type' => 'string', 'example' => 'unionid123'],
                                                                'email_verified_at' => ['type' => 'string', 'nullable' => true, 'example' => null],
                                                                'created_at' => ['type' => 'string', 'format' => 'date-time'],
                                                                'updated_at' => ['type' => 'string', 'format' => 'date-time']
                                                            ]
                                                        ]
                                                    ]
                                                ]
                                            ]
                                        ]
                                    ]
                                ]
                            ],
                            '400' => [
                                'description' => '请求错误',
                                'content' => [
                                    'application/json' => [
                                        'schema' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'code' => ['type' => 'integer', 'example' => 0],
                                                'status' => ['type' => 'string', 'example' => 'error'],
                                                'message' => ['type' => 'string', 'example' => '微信登录失败: code无效']
                                            ]
                                        ]
                                    ]
                                ]
                            ],
                            '422' => [
                                'description' => '验证失败',
                                'content' => [
                                    'application/json' => [
                                        'schema' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'code' => ['type' => 'integer', 'example' => 0],
                                                'status' => ['type' => 'string', 'example' => 'error'],
                                                'message' => ['type' => 'string', 'example' => '参数错误'],
                                                'errors' => [
                                                    'type' => 'object',
                                                    'properties' => [
                                                        'code' => [
                                                            'type' => 'array',
                                                            'items' => ['type' => 'string'],
                                                            'example' => ['code字段是必需的']
                                                        ]
                                                    ]
                                                ]
                                            ]
                                        ]
                                    ]
                                ]
                            ],
                            '500' => [
                                'description' => '服务器错误',
                                'content' => [
                                    'application/json' => [
                                        'schema' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'code' => ['type' => 'integer', 'example' => 0],
                                                'status' => ['type' => 'string', 'example' => 'error'],
                                                'message' => ['type' => 'string', 'example' => '服务器内部错误']
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ],
                '/api/auth/me' => [
                    'get' => [
                        'tags' => ['认证管理'],
                        'summary' => '获取当前用户信息',
                        'description' => '获取当前登录用户的详细信息',
                        'security' => [['sanctum' => []]],
                        'responses' => [
                            '200' => [
                                'description' => '获取成功',
                                'content' => [
                                    'application/json' => [
                                        'schema' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'code' => ['type' => 'integer', 'example' => 1],
                                                'status' => ['type' => 'string', 'example' => 'success'],
                                                'message' => ['type' => 'string', 'example' => '获取用户信息成功'],
                                                'data' => [
                                                    'type' => 'object',
                                                    'properties' => [
                                                        'id' => ['type' => 'integer', 'example' => 1],
                                                        'name' => ['type' => 'string', 'example' => '微信用户_12345678'],
                                                        'email' => ['type' => 'string', 'example' => 'openid123@wechat.local'],
                                                        'phone' => ['type' => 'string', 'nullable' => true, 'example' => null],
                                                        'weixin_mini_openid' => ['type' => 'string', 'example' => 'openid123'],
                                                        'weixin_unionid' => ['type' => 'string', 'example' => 'unionid123'],
                                                        'email_verified_at' => ['type' => 'string', 'nullable' => true, 'example' => null],
                                                        'created_at' => ['type' => 'string', 'format' => 'date-time'],
                                                        'updated_at' => ['type' => 'string', 'format' => 'date-time']
                                                    ]
                                                ]
                                            ]
                                        ]
                                    ]
                                ]
                            ],
                            '401' => [
                                'description' => '未授权',
                                'content' => [
                                    'application/json' => [
                                        'schema' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'code' => ['type' => 'integer', 'example' => 0],
                                                'status' => ['type' => 'string', 'example' => 'error'],
                                                'message' => ['type' => 'string', 'example' => '未授权访问']
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ],
                '/api/auth/logout' => [
                    'post' => [
                        'tags' => ['认证管理'],
                        'summary' => '退出登录',
                        'description' => '删除当前用户的访问令牌',
                        'security' => [['sanctum' => []]],
                        'responses' => [
                            '200' => [
                                'description' => '退出成功',
                                'content' => [
                                    'application/json' => [
                                        'schema' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'code' => ['type' => 'integer', 'example' => 1],
                                                'status' => ['type' => 'string', 'example' => 'success'],
                                                'message' => ['type' => 'string', 'example' => '退出成功'],
                                                'data' => ['type' => 'object', 'example' => []]
                                            ]
                                        ]
                                    ]
                                ]
                            ],
                            '401' => [
                                'description' => '未授权',
                                'content' => [
                                    'application/json' => [
                                        'schema' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'code' => ['type' => 'integer', 'example' => 0],
                                                'status' => ['type' => 'string', 'example' => 'error'],
                                                'message' => ['type' => 'string', 'example' => '未授权访问']
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            'components' => [
                'securitySchemes' => [
                    'sanctum' => [
                        'type' => 'http',
                        'scheme' => 'bearer',
                        'bearerFormat' => 'JWT'
                    ]
                ]
            ]
        ]);
    }
} 