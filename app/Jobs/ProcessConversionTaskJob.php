<?php

namespace App\Jobs;

use App\Models\FileConversionTask;
use App\Services\FFmpegService;
use App\Services\LibreOfficeService;
use App\Services\CloudConvertService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessConversionTaskJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300; // 5分钟超时
    public $tries = 3; // 重试3次

    protected FileConversionTask $task;
    protected FFmpegService $ffmpegService;
    protected LibreOfficeService $libreOfficeService;
    protected CloudConvertService $cloudConvertService;

    /**
     * 创建新的Job实例
     */
    public function __construct(
        FileConversionTask $task,
    ) {
        $this->task = $task;
        $this->ffmpegService = new FFmpegService();
        $this->libreOfficeService = new LibreOfficeService();
        $this->cloudConvertService = new CloudConvertService();
    }

    /**
     * 执行Job
     */
    public function handle(): void
    {
        try {
            Log::info('开始处理转换任务', ['task_id' => $this->task->id]);

            // 确定转换引擎
            $conversionEngine = $this->determineConversionEngine();

            // 更新任务的转换引擎
            $this->task->update(['conversion_engine' => $conversionEngine]);

            // 根据引擎提交转换任务
            $result = $this->submitConversionTask($conversionEngine);

            if ($result['success']) {
                Log::info('转换任务提交成功', [
                    'task_id' => $this->task->id,
                    'engine' => $conversionEngine,
                    'result' => $result
                ]);
            } else {
                Log::error('转换任务提交失败', [
                    'task_id' => $this->task->id,
                    'engine' => $conversionEngine,
                    'error' => $result['message']
                ]);

                // 标记任务失败
                $this->task->markAsFailed($result['message']);
            }
        } catch (\Exception $e) {
            Log::error('处理转换任务时发生异常', [
                'task_id' => $this->task->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // 标记任务失败
            $this->task->markAsFailed('任务处理异常: ' . $e->getMessage());
        }
    }

    /**
     * 确定转换引擎
     */
    protected function determineConversionEngine(): string
    {
        $inputFormat = $this->task->input_format;
        $outputFormat = $this->task->output_format;

        // 1. 优先检测FFmpeg支持
        if ($this->ffmpegService->supportsConversion($inputFormat, $outputFormat)) {
            return 'ffmpeg';
        }

        // 2. 其次检测LibreOffice支持
        if ($this->libreOfficeService->supportsConversion($inputFormat, $outputFormat)) {
            return 'libreoffice';
        }

        // 3. 兜底使用CloudConvert
        return 'cloudconvert';
    }

    /**
     * 提交转换任务
     */
    protected function submitConversionTask(string $engine): array
    {
        switch ($engine) {
            case 'ffmpeg':
                return $this->ffmpegService->submitConversionTask($this->task);
            case 'libreoffice':
                return $this->libreOfficeService->submitConversionTask($this->task);
            case 'cloudconvert':
            default:
                // return $this->cloudConvertService->submitConversionTask($this->task);
        }
        return [];
    }

    /**
     * Job失败时的处理
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('转换任务Job执行失败', [
            'task_id' => $this->task->id,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);

        // 标记任务失败
        $this->task->markAsFailed('Job执行失败: ' . $exception->getMessage());
    }
}
