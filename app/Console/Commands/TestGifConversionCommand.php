<?php

namespace App\Console\Commands;

use App\Models\FileConversionTask;
use App\Services\FFmpegService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TestGifConversionCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:gif-conversion {url} {--format=gif}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '测试GIF转换功能';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $url = $this->argument('url');
        $outputFormat = $this->option('format');

        $this->info("开始测试转换: {$url} -> {$outputFormat}");

        try {
            $ffmpegService = app(FFmpegService::class);

            // 检查FFmpeg是否可用
            if (!$ffmpegService->isFFmpegAvailable()) {
                $this->error('FFmpeg不可用');
                return 1;
            }

            // 创建测试任务
            $task = $ffmpegService->createConversionTask([
                'user_id' => null,
                'input_method' => FileConversionTask::INPUT_METHOD_URL,
                'input_file' => $url,
                'filename' => basename($url),
                'input_format' => pathinfo($url, PATHINFO_EXTENSION),
                'output_format' => $outputFormat,
                'conversion_options' => [
                    'videoQuality' => 'medium',
                    'framerate' => '10'
                ]
            ]);

            $this->info("任务已创建，ID: {$task->id}");

            // 调度任务
            $ffmpegService->dispatchConversionJob($task);

            $this->info("任务已调度，请查看队列处理进度");
            $this->info("可以使用以下命令查看任务状态:");
            $this->line("php artisan queue:work --once");
            
            return 0;

        } catch (\Exception $e) {
            $this->error("测试失败: " . $e->getMessage());
            Log::error('GIF转换测试失败', [
                'url' => $url,
                'format' => $outputFormat,
                'error' => $e->getMessage()
            ]);
            return 1;
        }
    }
}
