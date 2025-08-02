<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponseTrait;
use App\Models\ToolUsageLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;
use Illuminate\Support\Facades\Cache;

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
    private static string $cozeVideoWorkflowId = '7531615863444701219';



    /**
     * 提取抖音文案
     */
    #[OA\Post(
        path: '/api/tools/extract-douyin',
        summary: '提取抖音文案',
        description: '通过分享链接提取抖音视频文案（支持可选认证）',
        tags: ['工具接口'],
        security: [], // 可选认证，支持登录和未登录用户
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
                'share_url' => 'required|string',
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

            if (!isset($data['data'])) {
                Log::error('Coze API返回数据格式异常', [
                    'response' => $data,
                    'share_url' => $shareUrl,
                ]);

                return $this->error('提取结果格式异常', 500);
            }

            $data = $data['data'];
            $data = json_decode($data, true);

            // 检查API返回的数据
            if (!isset($data['output'])) {
                Log::error('Coze API返回数据格式异常', [
                    'response' => $data,
                    'share_url' => $shareUrl,
                ]);

                return $this->error('提取结果格式异常', 500);
            }

            $content = $data['output'];

            // 记录工具使用
            ToolUsageLog::recordUsage(
                toolName: '抖音提取文案',
                userId: $request->user()?->id
            );

            // 记录使用日志
            Log::info('抖音文案提取成功', [
                'user_id' => $request->user()?->id,
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

    /**
     * 解析抖音分享链接，提取无水印视频
     */
    #[OA\Post(
        path: '/api/tools/parse-douyin',
        summary: '解析抖音分享链接',
        description: '从抖音分享文本中提取无水印视频链接和信息（支持可选认证）',
        tags: ['工具接口'],
        security: [], // 可选认证，支持登录和未登录用户
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['share_text'],
                properties: [
                    new OA\Property(
                        property: 'share_text',
                        type: 'string',
                        description: '抖音分享文本或链接',
                        example: 'https://v.douyin.com/xxxxx/'
                    )
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: '解析成功',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 1),
                        new OA\Property(property: 'status', type: 'string', example: 'success'),
                        new OA\Property(property: 'message', type: 'string', example: '解析成功'),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'url', type: 'string', example: 'https://aweme.snssdk.com/aweme/v1/play/'),
                                new OA\Property(property: 'title', type: 'string', example: '视频标题'),
                                new OA\Property(property: 'video_id', type: 'string', example: '1234567890')
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(
                response: 400,
                description: '解析失败',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 0),
                        new OA\Property(property: 'status', type: 'string', example: 'error'),
                        new OA\Property(property: 'message', type: 'string', example: '未找到有效的分享链接'),
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
                                    property: 'share_text',
                                    type: 'array',
                                    items: new OA\Items(type: 'string'),
                                    example: ['share_text字段是必需的']
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
                        new OA\Property(property: 'message', type: 'string', example: '解析失败'),
                    ]
                )
            )
        ]
    )]
    public function parseDouyin(Request $request): JsonResponse
    {
        try {
            // 验证请求参数
            $request->validate([
                'share_text' => 'required|string|max:2000',
            ]);

            $shareText = $request->input('share_text');

            // 检查缓存
            $cacheKey = 'douyin_parse_' . md5($shareText);
            $cachedResult = Cache::get($cacheKey);
            if ($cachedResult) {
                            // 记录工具使用
            ToolUsageLog::recordUsage(
                toolName: '抖音视频解析',
                userId: $request->user()?->id
            );
                return $this->success($cachedResult, '解析成功（缓存）');
            }

            // 调用Coze API解析视频
            $response = Http::timeout(1200) // 20分钟超时
                ->withHeaders([
                    'Authorization' => 'Bearer ' . self::$cozeApiKey,
                    'Content-Type' => 'application/json',
                ])
                ->post(self::$cozeBaseUrl . '/v1/workflow/run', [
                    'workflow_id' => self::$cozeVideoWorkflowId,
                    'parameters' => [
                        'input' => $shareText,
                    ],
                ]);

            // 检查响应状态
            if (!$response->successful()) {
                Log::error('Coze API调用失败', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'share_text' => $shareText,
                ]);

                return $this->error('视频解析失败，请稍后重试', 500);
            }

            $data = $response->json();

            if (!isset($data['data'])) {
                Log::error('Coze API返回数据格式异常', [
                    'response' => $data,
                    'share_text' => $shareText,
                ]);

                return $this->error('解析结果格式异常', 500);
            }

            $data = $data['data'];
            $result = json_decode($data, true);

            // 检查API返回的数据结构
            if (!isset($result['output'])) {
                Log::error('Coze API返回数据格式异常', [
                    'response' => $result,
                    'share_text' => $shareText,
                ]);

                return $this->error('解析结果格式异常', 500);
            }

            $output = $result['output'];

            // 检查必要字段
            if (!isset($output['video_url']) || !isset($output['title'])) {
                Log::error('Coze API返回数据缺少必要字段', [
                    'output' => $output,
                    'share_text' => $shareText,
                ]);

                return $this->error('解析结果格式异常', 500);
            }

            // 构建返回结果
            $result = [
                'url' => $output['video_url'],
                'title' => $output['title'],
                'author' => $output['author'] ?? '',
                'cover' => $output['cover'] ?? '',
                'music_url' => $output['music_url'] ?? '',
                'video_duration' => $output['video_duration'] ?? 0,
                'video_id' => 'unknown', // Coze API 可能不提供 video_id
            ];

            // 缓存结果（1小时）
            Cache::put($cacheKey, $result, 3600);

            // 记录工具使用
            ToolUsageLog::recordUsage(
                toolName: '抖音视频解析',
                userId: $request->user()?->id
            );

            // 记录使用日志
            Log::info('抖音视频解析成功', [
                'user_id' => $request->user()?->id,
                'share_text' => $shareText,
                'title' => $result['title'],
                'author' => $result['author'],
                'video_duration' => $result['video_duration'],
            ]);

            return $this->success($result, '解析成功');
        } catch (ValidationException $e) {
            return $this->validationError($e->errors(), '参数错误');
        } catch (\Exception $e) {
            Log::error('抖音视频解析异常', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'share_text' => $request->input('share_text'),
            ]);

            return $this->serverError('解析失败: ' . $e->getMessage());
        }
    }



    /**
     * 记录工具使用
     */
    #[OA\Post(
        path: '/api/tools/record-usage',
        summary: '记录工具使用',
        description: '记录指定工具的一次使用',
        tags: ['工具接口'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['tool_name'],
                properties: [
                    new OA\Property(
                        property: 'tool_name',
                        type: 'string',
                        description: '工具名称',
                        example: '抖音提取文案'
                    )
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: '记录成功',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 1),
                        new OA\Property(property: 'status', type: 'string', example: 'success'),
                        new OA\Property(property: 'message', type: 'string', example: '使用记录已保存'),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'tool_name', type: 'string', example: '抖音提取文案'),
                                new OA\Property(property: 'user_id', type: 'integer', example: 1),
                                new OA\Property(property: 'used_at', type: 'string', format: 'date-time')
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(
                response: 400,
                description: '工具不存在',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 0),
                        new OA\Property(property: 'status', type: 'string', example: 'error'),
                        new OA\Property(property: 'message', type: 'string', example: '工具不存在'),
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
                                    property: 'tool_name',
                                    type: 'array',
                                    items: new OA\Items(type: 'string'),
                                    example: ['tool_name字段是必需的']
                                )
                            ]
                        )
                    ]
                )
            )
        ]
    )]
    public function recordUsage(Request $request): JsonResponse
    {
        try {
            // 验证请求参数
            $request->validate([
                'tool_name' => 'required|string|max:255',
            ]);

            $toolName = $request->input('tool_name');
            $userId = $request->user()->id;

            // 记录工具使用
            $usageLog = ToolUsageLog::recordUsage($toolName, $userId);

            if (!$usageLog) {
                return $this->error('工具不存在', 400);
            }

            // 记录日志
            Log::info('工具使用记录已保存', [
                'user_id' => $userId,
                'tool_name' => $toolName,
                'usage_log_id' => $usageLog->id,
            ]);

            return $this->success([
                'tool_name' => $toolName,
                'user_id' => $userId,
                'used_at' => $usageLog->used_at,
            ], '使用记录已保存');
        } catch (ValidationException $e) {
            return $this->validationError($e->errors(), '参数错误');
        } catch (\Exception $e) {
            Log::error('记录工具使用异常', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'tool_name' => $request->input('tool_name'),
                'user_id' => $request->user()->id ?? null,
            ]);

            return $this->serverError('记录失败');
        }
    }

    /**
     * 记录工具使用（无需认证）
     */
    #[OA\Post(
        path: '/api/tools/record-usage-public',
        summary: '记录工具使用（无需认证）',
        description: '记录指定工具的一次使用，支持未登录用户',
        tags: ['工具接口'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['tool_name'],
                properties: [
                    new OA\Property(
                        property: 'tool_name',
                        type: 'string',
                        description: '工具名称',
                        example: '视频转码'
                    )
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: '记录成功',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 1),
                        new OA\Property(property: 'status', type: 'string', example: 'success'),
                        new OA\Property(property: 'message', type: 'string', example: '使用记录已保存'),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'tool_name', type: 'string', example: '视频转码'),
                                new OA\Property(property: 'user_id', type: 'integer', nullable: true, example: null),
                                new OA\Property(property: 'used_at', type: 'string', format: 'date-time')
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(
                response: 400,
                description: '工具不存在',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 0),
                        new OA\Property(property: 'status', type: 'string', example: 'error'),
                        new OA\Property(property: 'message', type: 'string', example: '工具不存在'),
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
                                    property: 'tool_name',
                                    type: 'array',
                                    items: new OA\Items(type: 'string'),
                                    example: ['tool_name字段是必需的']
                                )
                            ]
                        )
                    ]
                )
            )
        ]
    )]
    public function recordUsagePublic(Request $request): JsonResponse
    {
        try {
            // 验证请求参数
            $request->validate([
                'tool_name' => 'required|string|max:255',
            ]);

            $toolName = $request->input('tool_name');
            $userId = $request->user()?->id; // 可能为null

            // 记录工具使用
            $usageLog = ToolUsageLog::recordUsage($toolName, $userId);

            if (!$usageLog) {
                return $this->error('工具不存在', 400);
            }

            // 记录日志
            Log::info('工具使用记录已保存（无需认证）', [
                'user_id' => $userId,
                'tool_name' => $toolName,
                'usage_log_id' => $usageLog->id,
            ]);

            return $this->success([
                'tool_name' => $toolName,
                'user_id' => $userId,
                'used_at' => $usageLog->used_at,
            ], '使用记录已保存');
        } catch (ValidationException $e) {
            return $this->validationError($e->errors(), '参数错误');
        } catch (\Exception $e) {
            Log::error('记录工具使用异常（无需认证）', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'tool_name' => $request->input('tool_name'),
                'user_id' => $request->user()?->id,
            ]);

            return $this->serverError('记录失败');
        }
    }
}
