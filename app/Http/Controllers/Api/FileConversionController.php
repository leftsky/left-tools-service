<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FileConversionTask;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class FileConversionController extends Controller
{
    /**
     * 提交视频转换任务
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function convert(Request $request): JsonResponse
    {
        try {
            // 验证请求参数
            $validator = Validator::make($request->all(), [
                'file_url' => 'required|url',
                'conversion_params' => 'array',
                'user_id' => 'nullable|integer'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => '参数验证失败',
                    'errors' => $validator->errors()
                ], 422);
            }

            $fileUrl = $request->input('file_url');
            $conversionParams = $request->input('conversion_params', []);
            $userId = $request->input('user_id');

            // 获取文件信息（简化版本，只获取基本信息）
            $fileInfo = $this->getBasicFileInfo($fileUrl);

            // 创建转换任务记录
            $task = FileConversionTask::create([
                'user_id' => $userId,
                'input_method' => FileConversionTask::INPUT_METHOD_URL,
                'input_file' => $fileUrl,
                'filename' => basename($fileUrl),
                'input_format' => $fileInfo['format'] ?? null,
                'file_size' => $fileInfo['file_size'] ?? 0,
                'output_format' => $conversionParams['target_format'] ?? 'mp4',
                'conversion_options' => $conversionParams,
                'status' => FileConversionTask::STATUS_WAIT,
            ]);

            Log::info('视频转换任务已提交', [
                'task_id' => $task->id,
                'file_url' => $fileUrl,
                'user_id' => $userId
            ]);

            return response()->json([
                'success' => true,
                'message' => '视频转换任务已提交',
                'data' => [
                    'task_id' => $task->id,
                    'status' => 'wait',
                    'filename' => $task->filename,
                    'file_size' => $task->formatted_file_size,
                    'output_format' => $task->output_format
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('视频转换任务提交失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => '服务器内部错误',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 获取任务状态
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function status(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'task_id' => 'required|integer'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => '参数验证失败',
                    'errors' => $validator->errors()
                ], 422);
            }

            $taskId = $request->input('task_id');
            $task = FileConversionTask::find($taskId);

            if (!$task) {
                return response()->json([
                    'success' => false,
                    'message' => '任务不存在'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'task_id' => $task->id,
                    'convertio_id' => $task->convertio_id,
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
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('获取任务状态失败', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => '获取任务状态失败',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 获取基本文件信息
     */
    protected function getBasicFileInfo(string $fileUrl): array
    {
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

            return [
                'filename' => $filename,
                'file_size' => $fileSize,
                'format' => $extension,
            ];
        } catch (\Exception $e) {
            Log::error('获取基本文件信息失败', ['error' => $e->getMessage()]);
            return [
                'filename' => 'unknown',
                'file_size' => 0,
                'format' => 'unknown',
            ];
        }
    }
}
