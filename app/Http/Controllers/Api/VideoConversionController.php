<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ConvertFileJob;
use App\Models\VideoConversionTask;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class VideoConversionController extends Controller
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

            // 生成临时文件路径
            $tempDir = storage_path('app/temp/video_conversion');
            if (!is_dir($tempDir)) {
                mkdir($tempDir, 0755, true);
            }

            $inputFilePath = $tempDir . '/' . Str::random(32) . '_input';
            $outputFilePath = $tempDir . '/' . Str::random(32) . '_output';

            // 下载文件到临时目录
            $downloadResult = $this->downloadFile($fileUrl, $inputFilePath);
            if (!$downloadResult['success']) {
                return response()->json([
                    'success' => false,
                    'message' => '文件下载失败',
                    'error' => $downloadResult['error']
                ], 400);
            }

            // 获取文件信息
            $fileInfo = $this->getFileInfo($inputFilePath);
            if (!$fileInfo) {
                return response()->json([
                    'success' => false,
                    'message' => '无法获取文件信息'
                ], 400);
            }

            // 创建转换任务记录
            $task = VideoConversionTask::create([
                'user_id' => $userId,
                'input_file_path' => $inputFilePath,
                'output_file_path' => $outputFilePath,
                'input_file_info' => $fileInfo,
                'conversion_params' => $conversionParams,
                'status' => VideoConversionTask::STATUS_PENDING,
            ]);

            // 分发转换任务
            $job = new ConvertFileJob($inputFilePath, $conversionParams);
            $jobId = dispatch($job);

            // 更新任务的job_id
            $task->update(['job_id' => $jobId]);

            Log::info('视频转换任务已提交', [
                'task_id' => $task->id,
                'job_id' => $jobId,
                'file_url' => $fileUrl,
                'user_id' => $userId
            ]);

            return response()->json([
                'success' => true,
                'message' => '视频转换任务已提交',
                'data' => [
                    'task_id' => $task->id,
                    'job_id' => $jobId,
                    'status' => 'pending',
                    'input_file_info' => $fileInfo
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
            $task = VideoConversionTask::find($taskId);

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
                    'status' => $task->status,
                    'status_text' => $task->status_text,
                    'input_file_info' => $task->input_file_info,
                    'output_file_info' => $task->output_file_info,
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
     * 下载文件
     */
    protected function downloadFile(string $url, string $filePath): array
    {
        try {
            $context = stream_context_create([
                'http' => [
                    'timeout' => 30,
                    'follow_location' => true,
                    'max_redirects' => 5
                ]
            ]);

            $content = file_get_contents($url, false, $context);
            if ($content === false) {
                return ['success' => false, 'error' => '无法下载文件'];
            }

            if (file_put_contents($filePath, $content) === false) {
                return ['success' => false, 'error' => '无法保存文件'];
            }

            return ['success' => true];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * 获取文件信息
     */
    protected function getFileInfo(string $filePath): ?array
    {
        try {
            $fileSize = filesize($filePath);
            $extension = pathinfo($filePath, PATHINFO_EXTENSION) ?: 'unknown';
            
            // 使用ffprobe获取视频信息
            $ffprobeCommand = "ffprobe -v quiet -print_format json -show_format -show_streams " . escapeshellarg($filePath);
            $output = shell_exec($ffprobeCommand);
            $videoInfo = json_decode($output, true);

            $fileInfo = [
                'filename' => basename($filePath),
                'file_size' => $fileSize,
                'format' => $extension,
            ];

            if ($videoInfo) {
                // 获取视频流信息
                $videoStream = null;
                $audioStream = null;
                
                foreach ($videoInfo['streams'] ?? [] as $stream) {
                    if ($stream['codec_type'] === 'video') {
                        $videoStream = $stream;
                    } elseif ($stream['codec_type'] === 'audio') {
                        $audioStream = $stream;
                    }
                }

                if ($videoStream) {
                    $fileInfo['video_codec'] = $videoStream['codec_name'] ?? null;
                    $fileInfo['width'] = $videoStream['width'] ?? null;
                    $fileInfo['height'] = $videoStream['height'] ?? null;
                    $fileInfo['resolution'] = ($fileInfo['width'] && $fileInfo['height']) ? 
                        $fileInfo['width'] . 'x' . $fileInfo['height'] : null;
                    $fileInfo['framerate'] = $this->parseFramerate($videoStream['r_frame_rate'] ?? null);
                    $fileInfo['bitrate'] = $videoStream['bit_rate'] ?? null;
                }

                if ($audioStream) {
                    $fileInfo['audio_codec'] = $audioStream['codec_name'] ?? null;
                    $fileInfo['audio_channels'] = $audioStream['channels'] ?? null;
                    $fileInfo['audio_sample_rate'] = $audioStream['sample_rate'] ?? null;
                    $fileInfo['audio_bitrate'] = $audioStream['bit_rate'] ?? null;
                }

                // 获取时长
                if (isset($videoInfo['format']['duration'])) {
                    $fileInfo['duration'] = (int) $videoInfo['format']['duration'];
                }
            }

            return $fileInfo;
        } catch (\Exception $e) {
            Log::error('获取文件信息失败', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * 解析帧率
     */
    protected function parseFramerate(?string $frameRate): ?float
    {
        if (!$frameRate) {
            return null;
        }

        if (strpos($frameRate, '/') !== false) {
            $parts = explode('/', $frameRate);
            if (count($parts) === 2 && $parts[1] != 0) {
                return (float) $parts[0] / (float) $parts[1];
            }
        }

        return (float) $frameRate;
    }
}
