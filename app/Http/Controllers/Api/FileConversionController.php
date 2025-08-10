<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FileConversionTask;
use App\Services\CloudConvertService;
use App\Services\ConvertioService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;

#[OA\Tag(
    name: '文件转换接口',
    description: '文件格式转换相关接口'
)]
class FileConversionController extends Controller
{
    use ApiResponseTrait;

    #[OA\Post(
        path: "/api/upload",
        tags: ["文件上传"],
        summary: "上传文件",
        description: "上传文件到阿里云存储",
        security: [["sanctum" => []]]
    )]
    #[OA\RequestBody(
        required: true,
        content: [
            new OA\MediaType(
                mediaType: "multipart/form-data",
                schema: new OA\Schema(
                    required: ["file"],
                    properties: [
                        new OA\Property(
                            property: "file",
                            type: "string",
                            format: "binary",
                            description: "要上传的文件"
                        ),
                        new OA\Property(
                            property: "type",
                            type: "string",
                            description: "文件类型，例如：image, document 等",
                            example: "image"
                        )
                    ]
                )
            )
        ]
    )]
    #[OA\Response(
        response: 200,
        description: "上传成功",
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: "success", type: "boolean", example: true),
                new OA\Property(property: "code", type: "integer", example: 200),
                new OA\Property(property: "message", type: "string", example: "文件上传成功"),
                new OA\Property(
                    property: "data",
                    properties: [
                        new OA\Property(property: "url", type: "string", example: "http://example.com/uploads/123.jpg"),
                        new OA\Property(property: "path", type: "string", example: "uploads/123.jpg")
                    ],
                    type: "object"
                )
            ]
        )
    )]
    #[OA\Response(
        response: 422,
        description: "验证失败",
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: "success", type: "boolean", example: false),
                new OA\Property(property: "code", type: "integer", example: 422),
                new OA\Property(property: "message", type: "string", example: "验证失败"),
                new OA\Property(property: "errors", type: "object")
            ]
        )
    )]
    #[OA\Response(
        response: 500,
        description: "上传失败",
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: "success", type: "boolean", example: false),
                new OA\Property(property: "code", type: "integer", example: 500),
                new OA\Property(property: "message", type: "string", example: "文件上传失败")
            ]
        )
    )]
    public function upload(Request $request)
    {
        $request->validate([
            'file' => 'required|file|max:10240', // 最大10MB
            'folder' => 'nullable|string'
        ]);

        $file = $request->file('file');
        if (!$file || !$file->isValid()) {
            return $this->error('未提供文件或文件上传失败');
        }

        try {
            $result = $this->uploadFileToStorage($file, $request->input('folder', 'uploads'));
            return $this->success($result, '文件上传成功');
        } catch (\Exception $e) {
            return $this->error('文件上传失败：' . $e->getMessage());
        }
    }

    /**
     * 提交视频转换任务
     */
    #[OA\Post(
        path: '/api/file-conversion/convert',
        summary: '提交文件转换任务',
        description: '通过URL提交文件转换任务（需要认证）',
        tags: ['文件转换接口'],
        security: [["sanctum" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['file_url', 'output_format'],
                properties: [
                    new OA\Property(
                        property: 'file_url',
                        type: 'string',
                        description: '文件URL地址',
                        example: 'https://example.com/video.mp4'
                    ),
                    new OA\Property(
                        property: 'output_format',
                        type: 'string',
                        description: '输出格式',
                        example: 'mp4'
                    ),
                    new OA\Property(
                        property: 'conversion_params',
                        type: 'object',
                        description: '转换参数（可选）',
                        properties: [
                            new OA\Property(
                                property: 'video_bitrate',
                                type: 'integer',
                                description: '视频比特率（单位：kbps）',
                                example: 2000
                            ),
                            new OA\Property(
                                property: 'video_resolution',
                                type: 'string',
                                description: '视频分辨率（格式：宽x高）',
                                example: '1920x1080'
                            ),
                            new OA\Property(
                                property: 'audio_bitrate',
                                type: 'integer',
                                description: '音频比特率（单位：kbps）',
                                example: 128
                            ),
                            new OA\Property(
                                property: 'audio_frequency',
                                type: 'integer',
                                description: '音频采样率（单位：Hz）',
                                example: 44100
                            ),
                            new OA\Property(
                                property: 'video_codec',
                                type: 'string',
                                description: '视频编解码器',
                                example: 'h264'
                            ),
                            new OA\Property(
                                property: 'audio_codec',
                                type: 'string',
                                description: '音频编解码器',
                                example: 'aac'
                            ),
                            new OA\Property(
                                property: 'quality',
                                type: 'string',
                                description: '输出质量（low, medium, high）',
                                example: 'high'
                            )
                        ]
                    )
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: '任务提交成功',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 1),
                        new OA\Property(property: 'status', type: 'string', example: 'success'),
                        new OA\Property(property: 'message', type: 'string', example: '视频转换任务已提交'),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'task_id', type: 'integer', example: 123),
                                new OA\Property(property: 'status', type: 'string', example: 'wait'),
                                new OA\Property(property: 'filename', type: 'string', example: 'video.mp4'),
                                new OA\Property(property: 'file_size', type: 'string', example: '10.5 MB'),
                                new OA\Property(property: 'output_format', type: 'string', example: 'mp4')
                            ]
                        )
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
                        new OA\Property(property: 'message', type: 'string', example: '参数验证失败'),
                        new OA\Property(
                            property: 'errors',
                            type: 'object',
                            properties: [
                                new OA\Property(
                                    property: 'file_url',
                                    type: 'array',
                                    items: new OA\Items(type: 'string'),
                                    example: ['file_url字段是必需的']
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
    public function convert(Request $request): JsonResponse
    {
        try {
            // 验证请求参数
            $validator = Validator::make($request->all(), [
                'file_url' => 'required|url',
                'output_format' => 'required|string|max:10',
                'conversion_params' => 'nullable|array',
                'conversion_params.video_bitrate' => 'nullable|integer|min:100|max:50000',
                'conversion_params.video_resolution' => 'nullable|string|regex:/^\d+x\d+$/',
                'conversion_params.audio_bitrate' => 'nullable|integer|min:32|max:320',
                'conversion_params.audio_frequency' => 'nullable|integer|in:8000,11025,22050,44100,48000',
                'conversion_params.video_codec' => 'nullable|string|max:20',
                'conversion_params.audio_codec' => 'nullable|string|max:20',
                'conversion_params.quality' => 'nullable|string|in:low,medium,high'
            ]);

            if ($validator->fails()) {
                return $this->validationError($validator->errors(), '参数验证失败');
            }

            $fileUrl = $request->input('file_url');
            $outputFormat = $request->input('output_format');
            $conversionParams = $request->input('conversion_params', []);
            $userId = $request->user()->id; // 从认证中获取用户ID（必须认证）

            // 获取基本文件信息
            try {
                $headers = get_headers($fileUrl, 1);
                $fileSize = 0;

                if ($headers && isset($headers['Content-Length'])) {
                    $fileSize = is_array($headers['Content-Length'])
                        ? (int) end($headers['Content-Length'])
                        : (int) $headers['Content-Length'];
                }

                $filename = basename(parse_url($fileUrl, PHP_URL_PATH));
                $extension = pathinfo($filename, PATHINFO_EXTENSION) ?: 'unknown';

                $fileInfo = [
                    'filename' => $filename,
                    'file_size' => $fileSize,
                    'format' => $extension,
                ];
            } catch (\Exception $e) {
                Log::error('获取基本文件信息失败', ['error' => $e->getMessage()]);
                $fileInfo = [
                    'filename' => 'unknown',
                    'file_size' => 0,
                    'format' => 'unknown',
                ];
            }

            // 创建转换任务记录
            $task = FileConversionTask::create([
                'user_id' => $userId,
                'input_method' => FileConversionTask::INPUT_METHOD_URL,
                'input_file' => $fileUrl,
                'filename' => basename($fileUrl),
                'input_format' => $fileInfo['format'] ?? null,
                'file_size' => $fileInfo['file_size'] ?? 0,
                'output_format' => $outputFormat,
                'conversion_options' => $conversionParams,
                'status' => FileConversionTask::STATUS_WAIT,
                'conversion_engine' => 'cloudconvert', // 固定使用 CloudConvert
            ]);

            // 提交转换任务
            $result = $this->submitConversionTask($task, $fileUrl, $conversionParams);

            if (!$result['success']) {
                return $this->error($result['message'], $result['code']);
            }

            return $this->success($result['data'], $result['message']);
        } catch (\Exception $e) {
            Log::error('文件转换任务提交失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->serverError('服务器内部错误', $e->getMessage());
        }
    }

    /**
     * 获取任务状态
     */
    #[OA\Get(
        path: '/api/file-conversion/status',
        summary: '获取任务状态',
        description: '获取文件转换任务的状态信息（需要认证）',
        tags: ['文件转换接口'],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(
                name: 'task_id',
                description: '任务ID',
                in: 'query',
                required: true,
                schema: new OA\Schema(type: 'integer', example: 123)
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: '获取状态成功',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 1),
                        new OA\Property(property: 'status', type: 'string', example: 'success'),
                        new OA\Property(property: 'message', type: 'string', example: '获取任务状态成功'),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'task_id', type: 'integer', example: 123),
                                new OA\Property(property: 'convertio_id', type: 'string', nullable: true, example: 'conv_123456'),
                                new OA\Property(property: 'cloudconvert_id', type: 'string', nullable: true, example: 'job_123456'),
                                new OA\Property(property: 'status', type: 'integer', example: 1),
                                new OA\Property(property: 'status_text', type: 'string', example: '转换中'),
                                new OA\Property(property: 'filename', type: 'string', example: 'video.mp4'),
                                new OA\Property(property: 'file_size', type: 'string', example: '10.5 MB'),
                                new OA\Property(property: 'output_format', type: 'string', example: 'mp4'),
                                new OA\Property(property: 'step_percent', type: 'integer', example: 50),
                                new OA\Property(property: 'output_url', type: 'string', nullable: true, example: 'https://example.com/output.mp4'),
                                new OA\Property(property: 'output_size', type: 'string', nullable: true, example: '8.2 MB'),
                                new OA\Property(property: 'error_message', type: 'string', nullable: true),
                                new OA\Property(property: 'started_at', type: 'string', format: 'date-time', nullable: true),
                                new OA\Property(property: 'completed_at', type: 'string', format: 'date-time', nullable: true),
                                new OA\Property(property: 'created_at', type: 'string', format: 'date-time')
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: '任务不存在',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 0),
                        new OA\Property(property: 'status', type: 'string', example: 'error'),
                        new OA\Property(property: 'message', type: 'string', example: '任务不存在'),
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
                        new OA\Property(property: 'message', type: 'string', example: '参数验证失败'),
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
                        new OA\Property(property: 'message', type: 'string', example: '获取任务状态失败'),
                    ]
                )
            )
        ]
    )]
    public function status(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'task_id' => 'required|integer'
            ]);

            if ($validator->fails()) {
                return $this->validationError($validator->errors(), '参数验证失败');
            }

            $taskId = $request->input('task_id');
            $task = FileConversionTask::find($taskId);

            if (!$task) {
                return $this->notFound('任务不存在');
            }

            // 主动查询转换引擎的状态
            $task->updateStatusFromEngine();

            return $this->success([
                'task_id' => $task->id,
                'convertio_id' => $task->convertio_id,
                'cloudconvert_id' => $task->cloudconvert_id,
                'status' => $task->status,
                'status_text' => $task->status_text,
                'filename' => $task->filename,
                'file_size' => $task->formatted_file_size,
                'output_format' => $task->output_format,
                'step_percent' => $task->step_percent,
                'minutes_used' => $task->minutes_used,
                'output_url' => $task->output_url,
                'output_size' => $task->formatted_output_size,
                'error_message' => $task->error_message,
                'started_at' => $task->started_at,
                'completed_at' => $task->completed_at,
                'created_at' => $task->created_at
            ], '获取任务状态成功');
        } catch (\Exception $e) {
            Log::error('获取任务状态失败', [
                'error' => $e->getMessage()
            ]);

            return $this->serverError('获取任务状态失败', $e->getMessage());
        }
    }



    /**
     * 获取支持的格式
     */
    #[OA\Get(
        path: '/api/file-conversion/formats',
        summary: '获取支持的格式',
        description: '获取系统支持的文件转换格式列表（需要认证）',
        tags: ['文件转换接口'],
        security: [["sanctum" => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: '获取成功',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 1),
                        new OA\Property(property: 'status', type: 'string', example: 'success'),
                        new OA\Property(property: 'message', type: 'string', example: '获取支持的格式成功'),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(
                                    property: 'video_formats',
                                    type: 'array',
                                    description: '支持的视频格式',
                                    items: new OA\Items(type: 'string'),
                                    example: ['mp4', 'avi', 'mov', 'wmv', 'flv', 'webm', 'mkv']
                                ),
                                new OA\Property(
                                    property: 'audio_formats',
                                    type: 'array',
                                    description: '支持的音频格式',
                                    items: new OA\Items(type: 'string'),
                                    example: ['mp3', 'wav', 'aac', 'ogg', 'flac', 'm4a']
                                ),
                                new OA\Property(
                                    property: 'image_formats',
                                    type: 'array',
                                    description: '支持的图片格式',
                                    items: new OA\Items(type: 'string'),
                                    example: ['jpg', 'png', 'gif', 'bmp', 'webp', 'svg']
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
                        new OA\Property(property: 'message', type: 'string', example: '获取支持的格式失败'),
                    ]
                )
            )
        ]
    )]
    public function getSupportedFormats(): JsonResponse
    {
        try {
            $cloudConvertService = app(CloudConvertService::class);
            $formats = $cloudConvertService->getSupportedFormats();

            return $this->success($formats, '获取支持的格式成功');
        } catch (\Exception $e) {
            Log::error('获取支持的格式失败', [
                'error' => $e->getMessage()
            ]);

            return $this->serverError('获取支持的格式失败', $e->getMessage());
        }
    }

    /**
     * 创建客户端直传任务
     */
    #[OA\Post(
        path: '/api/file-conversion/direct-upload',
        summary: '创建客户端直传任务',
        description: '创建客户端直传任务，获取上传URL和表单数据（需要认证）',
        tags: ['文件转换接口'],
        security: [["sanctum" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['filename', 'output_format'],
                properties: [
                    new OA\Property(
                        property: 'filename',
                        type: 'string',
                        description: '文件名（包含扩展名）',
                        example: 'video.mp4'
                    ),
                    new OA\Property(
                        property: 'output_format',
                        type: 'string',
                        description: '输出格式',
                        example: 'mp4'
                    ),
                    new OA\Property(
                        property: 'options',
                        type: 'array',
                        description: '转换选项',
                        items: new OA\Items(
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'key', type: 'string', example: 'video_bitrate'),
                                new OA\Property(property: 'value', type: 'string', example: '2000')
                            ]
                        )
                    ),
                    new OA\Property(
                        property: 'engine',
                        type: 'string',
                        description: '转换引擎',
                        enum: ['convertio', 'cloudconvert'],
                        example: 'cloudconvert'
                    )
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: '创建成功',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 1),
                        new OA\Property(property: 'status', type: 'string', example: 'success'),
                        new OA\Property(property: 'message', type: 'string', example: '客户端直传任务已创建'),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'task_id', type: 'integer', example: 123),
                                new OA\Property(property: 'upload_url', type: 'string', example: 'https://upload.cloudconvert.com/...'),
                                new OA\Property(property: 'form_data', type: 'object', example: ['key' => 'value']),
                                new OA\Property(property: 'filename', type: 'string', example: 'video.mp4'),
                                new OA\Property(property: 'output_format', type: 'string', example: 'mp4'),
                                new OA\Property(property: 'engine', type: 'string', example: 'cloudconvert')
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(
                response: 400,
                description: '格式不支持或引擎不支持',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 0),
                        new OA\Property(property: 'status', type: 'string', example: 'error'),
                        new OA\Property(property: 'message', type: 'string', example: 'Convertio 暂不支持客户端直传，请使用 CloudConvert 引擎'),
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
                        new OA\Property(property: 'message', type: 'string', example: '参数验证失败'),
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
                        new OA\Property(property: 'message', type: 'string', example: '创建客户端直传任务失败'),
                    ]
                )
            )
        ]
    )]
    public function createDirectUpload(Request $request): JsonResponse
    {
        try {
            // 验证请求参数
            $validator = Validator::make($request->all(), [
                'filename' => 'required|string|max:255',
                'output_format' => 'required|string|max:10',
                'options' => 'nullable|array',
                'options.*.key' => 'required_with:options|string',
                'options.*.value' => 'required_with:options',
                'engine' => 'string|in:convertio,cloudconvert'
            ]);

            if ($validator->fails()) {
                return $this->validationError($validator->errors(), '参数验证失败');
            }

            $filename = $request->input('filename');
            $outputFormat = $request->input('output_format', 'mp4');
            $options = $request->input('options', []);
            $engine = $request->input('engine', 'cloudconvert');
            $userId = $request->user()->id;

            // 检查文件格式
            $inputFormat = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            try {
                $cloudConvertService = app(CloudConvertService::class);
                $isFormatSupported = $cloudConvertService->validateFormat($inputFormat, $outputFormat);
            } catch (\Exception $e) {
                Log::error('格式验证失败', [
                    'input_format' => $inputFormat,
                    'output_format' => $outputFormat,
                    'error' => $e->getMessage()
                ]);
                $isFormatSupported = false;
            }
            
            if (!$isFormatSupported) {
                return $this->error("不支持从 {$inputFormat} 转换到 {$outputFormat}", 400);
            }

            // 处理转换选项
            $processedOptions = [];
            if (is_array($options)) {
                foreach ($options as $option) {
                    if (isset($option['key']) && isset($option['value'])) {
                        $processedOptions[$option['key']] = $option['value'];
                    }
                }
            }

            // 创建转换任务记录
            $task = FileConversionTask::create([
                'user_id' => $userId,
                'conversion_engine' => $engine,
                'input_method' => FileConversionTask::INPUT_METHOD_DIRECT_UPLOAD,
                'input_file' => $filename,
                'filename' => $filename,
                'input_format' => $inputFormat,
                'file_size' => 0, // 直传时还不知道文件大小
                'output_format' => $outputFormat,
                'conversion_options' => $options,
                'status' => FileConversionTask::STATUS_WAIT,
                'tag' => 'direct-upload-' . uniqid(),
            ]);

            // 根据引擎创建直传任务
            if ($engine === 'cloudconvert') {
                $cloudConvertService = app(CloudConvertService::class);
                $result = $cloudConvertService->createDirectUploadJob($filename, $outputFormat, $processedOptions);

                if (!$result['success']) {
                    $task->markAsFailed($result['error']);
                    return $this->serverError($result['error']);
                }

                // 更新任务记录
                $task->setCloudConvertId($result['data']['job_id']);
            } else {
                // Convertio 暂不支持直传，返回错误
                $task->markAsFailed('Convertio 暂不支持客户端直传');
                return $this->error('Convertio 暂不支持客户端直传，请使用 CloudConvert 引擎', 400);
            }

            Log::info('客户端直传任务已创建', [
                'task_id' => $task->id,
                'engine' => $engine,
                'filename' => $filename,
                'output_format' => $outputFormat
            ]);

            return $this->success([
                'task_id' => $task->id,
                'upload_url' => $result['data']['upload_url'],
                'form_data' => $result['data']['form_data'],
                'filename' => $task->filename,
                'output_format' => $outputFormat,
                'engine' => $engine
            ], '客户端直传任务已创建');
        } catch (\Exception $e) {
            Log::error('创建客户端直传任务失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->serverError('创建客户端直传任务失败', $e->getMessage());
        }
    }

    /**
     * 确认客户端直传完成
     */
    #[OA\Post(
        path: '/api/file-conversion/confirm-direct-upload',
        summary: '确认客户端直传完成',
        description: '确认客户端直传完成，开始转换任务（需要认证）',
        tags: ['文件转换接口'],
        security: [["sanctum" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['task_id'],
                properties: [
                    new OA\Property(
                        property: 'task_id',
                        type: 'integer',
                        description: '任务ID',
                        example: 123
                    )
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: '确认成功',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 1),
                        new OA\Property(property: 'status', type: 'string', example: 'success'),
                        new OA\Property(property: 'message', type: 'string', example: '客户端直传确认成功，开始转换'),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'task_id', type: 'integer', example: 123),
                                new OA\Property(property: 'status', type: 'string', example: 'processing')
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(
                response: 400,
                description: '任务类型错误或确认失败',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 0),
                        new OA\Property(property: 'status', type: 'string', example: 'error'),
                        new OA\Property(property: 'message', type: 'string', example: '该任务不是客户端直传任务'),
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: '任务不存在',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 0),
                        new OA\Property(property: 'status', type: 'string', example: 'error'),
                        new OA\Property(property: 'message', type: 'string', example: '任务不存在'),
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
                        new OA\Property(property: 'message', type: 'string', example: '参数验证失败'),
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
                        new OA\Property(property: 'message', type: 'string', example: '确认客户端直传失败'),
                    ]
                )
            )
        ]
    )]
    public function confirmDirectUpload(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'task_id' => 'required|integer'
            ]);

            if ($validator->fails()) {
                return $this->validationError($validator->errors(), '参数验证失败');
            }

            $taskId = $request->input('task_id');
            $task = FileConversionTask::find($taskId);

            if (!$task) {
                return $this->notFound('任务不存在');
            }

            if ($task->input_method !== FileConversionTask::INPUT_METHOD_DIRECT_UPLOAD) {
                return $this->error('该任务不是客户端直传任务', 400);
            }

            // 确认直传完成
            if ($task->isCloudConvertEngine() && $task->cloudconvert_id) {
                $cloudConvertService = app(CloudConvertService::class);
                $result = $cloudConvertService->confirmDirectUpload($task->cloudconvert_id);

                if (!$result['success']) {
                    return $this->error($result['error'], 400);
                }

                // 更新任务状态
                $task->startProcessing();

                return $this->success([
                    'task_id' => $task->id,
                    'status' => 'processing'
                ], '客户端直传确认成功，开始转换');
            }

            return $this->error('不支持的转换引擎', 400);
        } catch (\Exception $e) {
            Log::error('确认客户端直传失败', [
                'error' => $e->getMessage()
            ]);

            return $this->serverError('确认客户端直传失败', $e->getMessage());
        }
    }

    /**
     * 获取转换历史
     */
    #[OA\Get(
        path: '/api/file-conversion/history',
        summary: '获取转换历史',
        description: '获取用户的文件转换历史记录（需要认证）',
        tags: ['文件转换接口'],
        security: [["sanctum" => []]],
        parameters: [
            new OA\Parameter(
                name: 'limit',
                description: '每页记录数',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer', default: 20, example: 20)
            ),
            new OA\Parameter(
                name: 'page',
                description: '页码',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer', default: 1, example: 1)
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: '获取成功',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 1),
                        new OA\Property(property: 'status', type: 'string', example: 'success'),
                        new OA\Property(property: 'message', type: 'string', example: '获取转换历史成功'),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(
                                    property: 'tasks',
                                    type: 'array',
                                    description: '任务列表',
                                    items: new OA\Items(
                                        type: 'object',
                                        properties: [
                                            new OA\Property(property: 'id', type: 'integer', example: 123),
                                            new OA\Property(property: 'filename', type: 'string', example: 'video.mp4'),
                                            new OA\Property(property: 'status', type: 'integer', example: 2),
                                            new OA\Property(property: 'status_text', type: 'string', example: '已完成'),
                                            new OA\Property(property: 'input_format', type: 'string', example: 'avi'),
                                            new OA\Property(property: 'output_format', type: 'string', example: 'mp4'),
                                            new OA\Property(property: 'step_percent', type: 'integer', example: 100),
                                            new OA\Property(property: 'output_url', type: 'string', nullable: true),
                                            new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
                                            new OA\Property(property: 'completed_at', type: 'string', format: 'date-time', nullable: true)
                                        ]
                                    )
                                ),
                                new OA\Property(
                                    property: 'pagination',
                                    type: 'object',
                                    properties: [
                                        new OA\Property(property: 'current_page', type: 'integer', example: 1),
                                        new OA\Property(property: 'last_page', type: 'integer', example: 5),
                                        new OA\Property(property: 'per_page', type: 'integer', example: 20),
                                        new OA\Property(property: 'total', type: 'integer', example: 100)
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
                        new OA\Property(property: 'message', type: 'string', example: '获取转换历史失败'),
                    ]
                )
            )
        ]
    )]
    public function getConversionHistory(Request $request): JsonResponse
    {
        try {
            $userId = $request->user()->id; // 必须认证
            $limit = $request->input('limit', 20);
            $page = $request->input('page', 1);

            $tasks = FileConversionTask::where('user_id', $userId)
                ->orderBy('created_at', 'desc')
                ->paginate($limit, ['*'], 'page', $page);

            return $this->success([
                'tasks' => $tasks->items(),
                'pagination' => [
                    'current_page' => $tasks->currentPage(),
                    'last_page' => $tasks->lastPage(),
                    'per_page' => $tasks->perPage(),
                    'total' => $tasks->total()
                ]
            ], '获取转换历史成功');
        } catch (\Exception $e) {
            Log::error('获取转换历史失败', [
                'error' => $e->getMessage()
            ]);

            return $this->serverError('获取转换历史失败', $e->getMessage());
        }
    }

    /**
     * 上传文件到存储
     */
    protected function uploadFileToStorage($file, string $folder): array
    {
        $extension = $file->getClientOriginalExtension();
        $fileName = Str::random(10) . '_' . time() . '.' . $extension;
        $folder = trim($folder, '/');
        $filePath = $folder . '/' . $fileName;

        $disk = Storage::disk('oss');
        $content = file_get_contents($file->getRealPath());
        $disk->put($filePath, $content);

        return [
            'url' => Storage::url($filePath),
            'path' => $filePath
        ];
    }

    /**
     * 提交转换任务
     */
    protected function submitConversionTask(FileConversionTask $task, string $fileUrl, array $conversionParams): array
    {
        try {
            $cloudConvertService = app(CloudConvertService::class);
            // 提取转换选项
            $allowedOptions = [
                'video_bitrate',
                'video_resolution',
                'audio_bitrate',
                'audio_frequency',
                'video_codec',
                'audio_codec',
                'quality'
            ];
            $conversionOptions = array_intersect_key($conversionParams, array_flip($allowedOptions));

            $result = $cloudConvertService->startConversion([
                'input_url' => $fileUrl,
                'output_format' => $task->output_format,
                'options' => $conversionOptions,
                'tag' => 'task-' . $task->id
            ]);

            if ($result['success']) {
                // 更新任务为已开始状态
                $task->update([
                    'status' => FileConversionTask::STATUS_CONVERT,
                    'cloudconvert_id' => $result['data']['job_id'],
                    'conversion_engine' => 'cloudconvert'
                ]);

                Log::info('文件转换任务已提交到 CloudConvert', [
                    'task_id' => $task->id,
                    'cloudconvert_id' => $result['data']['job_id'],
                    'file_url' => $fileUrl,
                    'output_format' => $task->output_format
                ]);

                return [
                    'success' => true,
                    'message' => '文件转换任务已提交到 CloudConvert',
                    'data' => [
                        'task_id' => $task->id,
                        'status' => 'processing',
                        'cloudconvert_id' => $result['data']['job_id'],
                        'filename' => $task->filename,
                        'file_size' => $task->formatted_file_size,
                        'input_format' => $task->input_format,
                        'output_format' => $task->output_format,
                        'conversion_options' => $conversionParams
                    ]
                ];
            }

            // 标记任务为失败状态
            $task->update(['status' => FileConversionTask::STATUS_FAILED]);
            Log::error('转换任务失败', [
                'task_id' => $task->id,
                'error' => $result['error']
            ]);

            return [
                'success' => false,
                'message' => 'CloudConvert 转换任务提交失败: ' . $result['error'],
                'code' => 500
            ];
        } catch (\Exception $e) {
            // 标记任务为失败状态
            $task->update(['status' => FileConversionTask::STATUS_FAILED]);
            Log::error('转换任务失败', [
                'task_id' => $task->id,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'CloudConvert 服务调用失败: ' . $e->getMessage(),
                'code' => 500
            ];
        }
    }
}
