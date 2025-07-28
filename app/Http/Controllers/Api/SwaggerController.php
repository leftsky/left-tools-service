<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: '小左子的工具箱 API',
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
#[OA\Tag(
    name: '工具接口',
    description: '各种工具功能接口'
)]
class SwaggerController extends Controller
{
    // 这个控制器只用于提供OpenAPI注解，不需要任何方法
} 