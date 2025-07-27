<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

#[OA\Tag(
    name: '工具接口',
    description: '各种工具功能接口'
)]
class ToolController extends Controller
{
    use ApiResponseTrait;

    // Coze API配置
    private static string $cozeBaseUrl = 'https://api.coze.cn';
    private static string $cozeApiKey = 'pat_ENxu6TK2M55Wimhh6pL0UAO8lDY6O3SgFRXiaZNMtAOAZI3t5rSBGT0uRrlaOgfY';
    private static string $cozeWorkflowId = '7530571901480648767';

    /**
     * 提取抖音文案
     */
    #[OA\Post(
        path: '/api/tools/extract-douyin',
        summary: '提取抖音文案',
        description: '通过分享链接提取抖音视频文案',
        tags: ['工具接口'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['share_url'],
                properties: [
                    new OA\Property(
                        property: 'share_url',
                        type: 'string',
                        description: '抖音分享链接',
                        example: 'https://v.douyin.com/xxxxx/'
                    )
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: '提取成功',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 1),
                        new OA\Property(property: 'status', type: 'string', example: 'success'),
                        new OA\Property(property: 'message', type: 'string', example: '提取成功'),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'content', type: 'string', example: '提取的文案内容'),
                                new OA\Property(property: 'original_url', type: 'string', example: 'https://v.douyin.com/xxxxx/')
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
                        new OA\Property(property: 'message', type: 'string', example: '链接格式错误'),
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
                                    property: 'share_url',
                                    type: 'array',
                                    items: new OA\Items(type: 'string'),
                                    example: ['share_url字段是必需的']
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
                        new OA\Property(property: 'message', type: 'string', example: '提取失败'),
                    ]
                )
            )
        ]
    )]
    public function extractDouyin(Request $request): JsonResponse
    {
        try {
            // 验证请求参数
            $request->validate([
                'share_url' => 'required|string|url',
            ]);

            $shareUrl = $request->input('share_url');

            // 验证是否为抖音链接
            if (!str_contains($shareUrl, 'douyin.com')) {
                return $this->error('请提供有效的抖音分享链接', 400);
            }

            // 调用Coze API
            $response = Http::timeout(1200) // 20分钟超时
                ->withHeaders([
                    'Authorization' => 'Bearer ' . self::$cozeApiKey,
                    'Content-Type' => 'application/json',
                ])
                ->post(self::$cozeBaseUrl . '/v1/workflow/run', [
                    'workflow_id' => self::$cozeWorkflowId,
                    'parameters' => [
                        'input' => $shareUrl,
                    ],
                ]);

            // 检查响应状态
            if (!$response->successful()) {
                Log::error('Coze API调用失败', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'share_url' => $shareUrl,
                ]);

                return $this->error('文案提取失败，请稍后重试', 500);
            }

            $data = $response->json();

            // 检查API返回的数据
            if (!isset($data['data']) || !isset($data['data']['output'])) {
                Log::error('Coze API返回数据格式异常', [
                    'response' => $data,
                    'share_url' => $shareUrl,
                ]);

                return $this->error('提取结果格式异常', 500);
            }

            $content = $data['data']['output'];

            // 记录使用日志
            Log::info('抖音文案提取成功', [
                'user_id' => $request->user()->id,
                'share_url' => $shareUrl,
                'content_length' => strlen($content),
            ]);

            return $this->success([
                'content' => $content,
                'original_url' => $shareUrl,
            ], '提取成功');
        } catch (ValidationException $e) {
            return $this->validationError($e->errors(), '参数错误');
        } catch (\Exception $e) {
            Log::error('抖音文案提取异常', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'share_url' => $request->input('share_url'),
            ]);

            return $this->serverError('提取失败');
        }
    }
}
