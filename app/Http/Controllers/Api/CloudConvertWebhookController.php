<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FileConversionTask;
use App\Services\CloudConvertService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Exception;

class CloudConvertWebhookController extends Controller
{
    /**
     * 处理CloudConvert的webhook回调
     */
    public function handle(Request $request)
    {
        try {
            $jobData = $request->json()->all();
            
            Log::info('收到CloudConvert webhook回调', ['data' => $jobData]);

            // 验证基本数据结构
            if (!isset($jobData['job']['id']) || !isset($jobData['job']['status'])) {
                Log::warning('CloudConvert webhook数据格式无效', ['data' => $jobData]);
                return response()->json(['status' => 'invalid_data'], 400);
            }

            $jobId = $jobData['job']['id'];
            $status = $jobData['job']['status'];
            $event = $jobData['event'] ?? 'unknown';

            // 查找对应的任务记录
            $task = FileConversionTask::where('cloudconvert_job_id', $jobId)->first();
            if (!$task) {
                Log::warning('未找到对应的任务记录', ['job_id' => $jobId]);
                return response()->json(['status' => 'task_not_found'], 404);
            }

            // 根据状态处理
            if ($status === 'finished' && $event === 'job.finished') {
                $this->handleSuccessfulJob($task, $jobId);
            } elseif ($status === 'error' || $event === 'job.failed') {
                $this->handleFailedJob($task, $jobData);
            } else {
                Log::info('CloudConvert任务状态更新', [
                    'job_id' => $jobId,
                    'status' => $status,
                    'event' => $event
                ]);
            }

            return response()->json(['status' => 'ok']);
        } catch (Exception $e) {
            Log::error('CloudConvert webhook处理失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'webhook处理失败'], 500);
        }
    }

    /**
     * 处理成功完成的任务
     */
    private function handleSuccessfulJob(FileConversionTask $task, string $jobId): void
    {
        try {
            Log::info('开始处理成功完成的任务', ['task_id' => $task->id, 'job_id' => $jobId]);

            // 1. 从CloudConvert下载文件到临时目录
            $cloudConvertService = app(CloudConvertService::class);
            $tempFilePath = $cloudConvertService->downloadResultToTemp($jobId);

            if (!$tempFilePath) {
                throw new Exception('从CloudConvert下载文件失败');
            }

            // 2. 上传到OSS
            $ossResult = $this->uploadToOSS($tempFilePath, $task);

            // 3. 更新任务状态
            $task->update([
                'status' => FileConversionTask::STATUS_FINISH,
                'output_url' => $ossResult['url'],
                'completed_at' => now()
            ]);

            Log::info('任务处理完成', [
                'task_id' => $task->id,
                'job_id' => $jobId,
                'output_url' => $ossResult['url']
            ]);

        } catch (Exception $e) {
            Log::error('处理成功任务失败', [
                'task_id' => $task->id,
                'job_id' => $jobId,
                'error' => $e->getMessage()
            ]);

            // 更新任务状态为失败
            $task->update([
                'status' => FileConversionTask::STATUS_FAILED,
                'error_message' => '文件下载或上传失败: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * 处理失败的任务
     */
    private function handleFailedJob(FileConversionTask $task, array $jobData): void
    {
        $errorMessage = $jobData['job']['message'] ?? '转换失败';
        
        $task->update([
            'status' => FileConversionTask::STATUS_FAILED,
            'error_message' => $errorMessage
        ]);

        Log::info('任务标记为失败', [
            'task_id' => $task->id,
            'job_id' => $jobData['job']['id'],
            'error' => $errorMessage
        ]);
    }

    /**
     * 上传文件到OSS
     */
    private function uploadToOSS(string $tempFilePath, FileConversionTask $task): array
    {
        try {
            // 生成文件名
            $extension = pathinfo($tempFilePath, PATHINFO_EXTENSION);
            $fileName = 'converted_' . $task->id . '_' . time() . '.' . $extension;
            $folder = 'conversions';
            $filePath = $folder . '/' . $fileName;

            // 上传到OSS
            $disk = Storage::disk('oss');
            $content = file_get_contents($tempFilePath);
            $disk->put($filePath, $content);

            // 删除临时文件
            unlink($tempFilePath);

            $result = [
                'url' => Storage::url($filePath),
                'path' => $filePath
            ];

            Log::info('文件上传到OSS成功', [
                'task_id' => $task->id,
                'file_path' => $filePath,
                'file_size' => strlen($content)
            ]);

            return $result;
        } catch (Exception $e) {
            // 确保删除临时文件
            if (file_exists($tempFilePath)) {
                unlink($tempFilePath);
            }
            throw $e;
        }
    }
}
