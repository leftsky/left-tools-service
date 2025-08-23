<?php

namespace App\Jobs;

use App\Models\FileConversionTask;
use App\Services\FFmpegService;
use App\Services\LibreOfficeService;
use App\Services\ImageMagickService;
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
    protected ImageMagickService $imageMagickService;
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
        $this->imageMagickService = new ImageMagickService();
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

        // 1. 优先检测ImageMagick支持（图片格式转换）
        if ($this->imageMagickService->supportsConversion($inputFormat, $outputFormat)) {
            return 'imagemagick';
        }

        // 2. 其次检测FFmpeg支持（音视频格式转换）
        if ($this->ffmpegService->supportsConversion($inputFormat, $outputFormat)) {
            return 'ffmpeg';
        }

        // 3. 再次检测LibreOffice支持（文档格式转换）
        if ($this->libreOfficeService->supportsConversion($inputFormat, $outputFormat)) {
            return 'libreoffice';
        }

        // 4. 兜底使用CloudConvert
        return 'cloudconvert';
    }

    /**
     * 提交转换任务
     */
    protected function submitConversionTask(string $engine): array
    {
        switch ($engine) {
            case 'imagemagick':
                return $this->imageMagickService->submitConversionTask($this->task);
            case 'ffmpeg':
                return $this->ffmpegService->submitConversionTask($this->task);
            case 'libreoffice':
                return $this->libreOfficeService->submitConversionTask($this->task);
            case 'cloudconvert':
            default:
                // 如果是local则不使用cloudconvert
                if (config('app.env') === 'local') {
                    Log::error('local环境不使用cloudconvert', [
                        'task_id' => $this->task->id,
                        'engine' => $engine
                    ]);
                    break;
                }
                // return $this->cloudConvertService->submitConversionTask($this->task);
                // 使用旧方式调用cloudconvert
                $result =  $this->cloudConvertService->startConversion([
                    'input_url' => $this->task->input_file,
                    'output_format' => $this->task->output_format,
                    'options' => $this->task->conversion_options,
                    'tag' => "task-" . $this->task->id,
                ]);

                if ($result['success']) {
                    // 更新任务为已开始状态
                    $this->task->update([
                        'status' => FileConversionTask::STATUS_CONVERT,
                        'cloudconvert_id' => $result['data']['job_id'],
                        'conversion_engine' => 'cloudconvert'
                    ]);

                    Log::info('文件转换任务已提交到 CloudConvert', [
                        'task_id' => $this->task->id,
                        'cloudconvert_id' => $result['data']['job_id'],
                        'file_url' => $this->task->input_file,
                        'output_format' => $this->task->output_format
                    ]);

                    return [
                        'success' => true,
                        'message' => '文件转换任务已提交到 CloudConvert',
                        'data' => [
                            'task_id' => $this->task->id,
                            'status' => 'processing',
                            'cloudconvert_id' => $result['data']['job_id'],
                            'filename' => $this->task->filename,
                            'file_size' => $this->task->formatted_file_size,
                            'input_format' => $this->task->input_format,
                            'output_format' => $this->task->output_format,
                            'conversion_options' => $this->task->conversion_options
                        ]
                    ];
                }

                // 标记任务为失败状态
                $this->task->update(['status' => FileConversionTask::STATUS_FAILED]);
                Log::error('转换任务失败', [
                    'task_id' => $this->task->id,
                    'error' => $result['error']
                ]);
                break;
        }
        return [
            'success' => false,
            'message' => '不支持的转换引擎',
            'code' => 400
        ];
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
