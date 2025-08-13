<?php

namespace App\Jobs;

use App\Models\FileConversionTask;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Exception;

class FFmpegConversionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected FileConversionTask $task;

    /**
     * 任务超时时间（秒）
     */
    public $timeout = 1800; // 30分钟

    /**
     * 最大尝试次数
     */
    public $tries = 3;

    /**
     * 支持的输出格式配置
     */
    const OUTPUT_FORMATS = [
        'mp4', 'avi', 'mov', 'mkv', 'wmv', 'flv', 'webm',
        'm4v', '3gp', 'ogv', 'ts', 'mts', 'asf', 'vob',
        'mpg', 'mpeg', 'divx', 'xvid', 'swf', 'f4v',
        'm2ts', 'mxf', 'gif', 'apng', 'webp', 'avif', 'heic', 'heif'
    ];

    /**
     * 分辨率映射
     */
    const RESOLUTION_MAP = [
        '4k' => '3840:2160',
        '1080p' => '1920:1080',
        '720p' => '1280:720',
        '480p' => '854:480'
    ];

    /**
     * 质量CRF值映射
     */
    const QUALITY_CRF_MAP = [
        'high' => 18,
        'medium' => 23,
        'low' => 28
    ];

    /**
     * 文件大小限制（100MB）
     */
    const MAX_FILE_SIZE = 100 * 1024 * 1024;

    /**
     * 创建新的任务实例
     */
    public function __construct(FileConversionTask $task)
    {
        $this->task = $task;
    }

    /**
     * 执行任务
     */
    public function handle(): void
    {
        try {
            Log::info('开始FFmpeg转换任务', [
                'task_id' => $this->task->id,
                'input_format' => $this->task->input_format,
                'output_format' => $this->task->output_format
            ]);

            // 更新任务状态为转换中
            $this->task->startProcessing();

            // 验证输出格式
            if (!in_array($this->task->output_format, self::OUTPUT_FORMATS)) {
                throw new Exception("不支持的输出格式: {$this->task->output_format}");
            }

            // 下载输入文件
            $inputFilePath = $this->downloadInputFile();

            // 验证文件大小
            $fileSize = filesize($inputFilePath);
            if ($fileSize > self::MAX_FILE_SIZE) {
                throw new Exception("文件过大，请选择小于100MB的文件");
            }

            // 执行转换
            $outputFilePath = $this->performConversion($inputFilePath);

            // 上传结果文件
            $outputUrl = $this->uploadOutputFile($outputFilePath);

            // 更新任务状态为完成
            $outputSize = filesize($outputFilePath);
            $this->task->update([
                'status' => FileConversionTask::STATUS_FINISH,
                'output_url' => $outputUrl,
                'output_size' => $outputSize,
                'step_percent' => 100,
                'completed_at' => now()
            ]);

            // 清理临时文件
            $this->cleanupFiles($inputFilePath, $outputFilePath);

            Log::info('FFmpeg转换任务完成', [
                'task_id' => $this->task->id,
                'output_url' => $outputUrl,
                'output_size' => $outputSize
            ]);

        } catch (Exception $e) {
            Log::error('FFmpeg转换任务失败', [
                'task_id' => $this->task->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // 更新任务状态为失败
            $this->task->update([
                'status' => FileConversionTask::STATUS_FAILED,
                'error_message' => $e->getMessage(),
                'completed_at' => now()
            ]);

            // 清理可能的临时文件
            if (isset($inputFilePath) && file_exists($inputFilePath)) {
                unlink($inputFilePath);
            }
            if (isset($outputFilePath) && file_exists($outputFilePath)) {
                unlink($outputFilePath);
            }

            throw $e;
        }
    }

    /**
     * 下载输入文件到临时目录
     */
    protected function downloadInputFile(): string
    {
        $this->task->updateProgress(5);

        $tempDir = storage_path('app/temp');
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $inputExt = $this->task->input_format;
        $tempInputFile = $tempDir . '/input_' . $this->task->id . '_' . time() . '.' . $inputExt;

        // 根据输入方式下载文件
        switch ($this->task->input_method) {
            case FileConversionTask::INPUT_METHOD_URL:
                $this->downloadFromUrl($this->task->input_file, $tempInputFile);
                break;

            case FileConversionTask::INPUT_METHOD_UPLOAD:
            case FileConversionTask::INPUT_METHOD_DIRECT_UPLOAD:
                // 从存储中复制文件
                $content = Storage::get($this->task->input_file);
                file_put_contents($tempInputFile, $content);
                break;

            default:
                throw new Exception("不支持的输入方式: {$this->task->input_method}");
        }

        if (!file_exists($tempInputFile)) {
            throw new Exception('输入文件下载失败');
        }

        $this->task->updateProgress(10);
        return $tempInputFile;
    }

    /**
     * 从URL下载文件
     */
    protected function downloadFromUrl(string $url, string $targetPath): void
    {
        $context = stream_context_create([
            'http' => [
                'timeout' => 300, // 5分钟超时
                'user_agent' => 'Mozilla/5.0 (compatible; FFmpegConverter/1.0)'
            ]
        ]);

        $content = file_get_contents($url, false, $context);
        if ($content === false) {
            throw new Exception("无法从URL下载文件: {$url}");
        }

        file_put_contents($targetPath, $content);
    }

    /**
     * 执行FFmpeg转换
     */
    protected function performConversion(string $inputFilePath): string
    {
        $tempDir = dirname($inputFilePath);
        $outputExt = $this->task->output_format;
        $outputFilePath = $tempDir . '/output_' . $this->task->id . '_' . time() . '.' . $outputExt;

        // 使用分离式转码（类似JS版本）
        $this->performSeparateTranscode($inputFilePath, $outputFilePath);

        if (!file_exists($outputFilePath)) {
            throw new Exception('转换失败，输出文件不存在');
        }

        return $outputFilePath;
    }

    /**
     * 分离式转码：分别处理视频和音频
     */
    protected function performSeparateTranscode(string $inputFilePath, string $outputFilePath): void
    {
        $tempDir = dirname($inputFilePath);
        $outputExt = $this->task->output_format;
        $options = $this->task->getConversionOptions();

        // 临时文件路径
        $videoOnlyFile = $tempDir . '/video_only_' . $this->task->id . '.' . $outputExt;
        $audioFile = $tempDir . '/audio_' . $this->task->id . ($outputExt === 'avi' ? '.mp3' : '.aac');

        try {
            // 第一步：转码视频（无音频）
            $this->task->updateProgress(20);
            $this->convertVideoOnly($inputFilePath, $videoOnlyFile, $options);

            // 第二步：提取并转码音频
            $this->task->updateProgress(50);
            $this->extractAudio($inputFilePath, $audioFile, $outputExt);

            // 第三步：合并视频和音频
            $this->task->updateProgress(80);
            $this->mergeVideoAndAudio($videoOnlyFile, $audioFile, $outputFilePath);

            $this->task->updateProgress(90);

        } finally {
            // 清理临时文件
            if (file_exists($videoOnlyFile)) {
                unlink($videoOnlyFile);
            }
            if (file_exists($audioFile)) {
                unlink($audioFile);
            }
        }
    }

    /**
     * 转码视频（无音频）
     */
    protected function convertVideoOnly(string $inputFile, string $outputFile, array $options): void
    {
        $command = ['ffmpeg', '-i', $inputFile];

        // 分辨率设置
        if (!empty($options['resolution']) && $options['resolution'] !== 'original') {
            $resolution = self::RESOLUTION_MAP[$options['resolution']] ?? null;
            if ($resolution) {
                $command = array_merge($command, ['-vf', "scale={$resolution}"]);
            }
        }

        // 帧率设置
        if (!empty($options['framerate']) && $options['framerate'] !== 'original') {
            $command = array_merge($command, ['-r', $options['framerate']]);
        }

        // 视频编码设置
        $crf = self::QUALITY_CRF_MAP[$options['videoQuality'] ?? 'medium'] ?? 23;
        $command = array_merge($command, [
            '-c:v', 'libx264',
            '-preset', 'ultrafast',
            '-crf', (string)$crf,
            '-an', // 跳过音频
            '-y', $outputFile
        ]);

        $this->executeFFmpegCommand($command);
    }

    /**
     * 提取音频
     */
    protected function extractAudio(string $inputFile, string $audioFile, string $outputExt): void
    {
        $command = ['ffmpeg', '-i', $inputFile, '-vn']; // 跳过视频

        if ($outputExt === 'avi') {
            // AVI 格式使用 MP3 音频编码
            $command = array_merge($command, [
                '-c:a', 'mp3',
                '-b:a', '128k',
                '-ar', '44100'
            ]);
        } else {
            // 其他格式使用 AAC 音频编码
            $command = array_merge($command, [
                '-c:a', 'aac',
                '-b:a', '128k',
                '-ar', '48000'
            ]);
        }

        $command = array_merge($command, ['-y', $audioFile]);

        $this->executeFFmpegCommand($command);
    }

    /**
     * 合并视频和音频
     */
    protected function mergeVideoAndAudio(string $videoFile, string $audioFile, string $outputFile): void
    {
        $command = [
            'ffmpeg',
            '-i', $videoFile,
            '-i', $audioFile,
            '-c:v', 'copy',
            '-c:a', 'copy',
            '-shortest',
            '-y', $outputFile
        ];

        $this->executeFFmpegCommand($command);
    }

    /**
     * 执行FFmpeg命令
     */
    protected function executeFFmpegCommand(array $command): void
    {
        $commandStr = implode(' ', array_map('escapeshellarg', $command));
        
        Log::info('执行FFmpeg命令', ['command' => $commandStr]);

        $descriptorspec = [
            0 => ['pipe', 'r'],  // stdin
            1 => ['pipe', 'w'],  // stdout
            2 => ['pipe', 'w']   // stderr
        ];

        $process = proc_open($commandStr, $descriptorspec, $pipes);

        if (!is_resource($process)) {
            throw new Exception('无法启动FFmpeg进程');
        }

        // 关闭stdin
        fclose($pipes[0]);

        // 读取stdout和stderr
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        // 等待进程结束
        $returnCode = proc_close($process);

        if ($returnCode !== 0) {
            Log::error('FFmpeg命令执行失败', [
                'command' => $commandStr,
                'return_code' => $returnCode,
                'stdout' => $stdout,
                'stderr' => $stderr
            ]);
            throw new Exception("FFmpeg转换失败: {$stderr}");
        }

        Log::info('FFmpeg命令执行成功', ['command' => $commandStr]);
    }

    /**
     * 上传输出文件到OSS
     */
    protected function uploadOutputFile(string $outputFilePath): string
    {
        $extension = pathinfo($outputFilePath, PATHINFO_EXTENSION);
        $randomNumber = rand(10000, 99999);
        $timestamp = date('Y-m-d H:i:s');
        $fileName = "格式转换大王 {$timestamp} {$randomNumber}.{$extension}";
        $folder = 'conversions';
        $filePath = $folder . '/' . $fileName;

        // 上传到OSS
        $disk = Storage::disk('oss');
        $content = file_get_contents($outputFilePath);
        $disk->put($filePath, $content);

        Log::info('文件上传到OSS完成', [
            'task_id' => $this->task->id,
            'filename' => $fileName,
            'file_size' => strlen($content)
        ]);

        return Storage::url($filePath);
    }

    /**
     * 清理临时文件
     */
    protected function cleanupFiles(string ...$files): void
    {
        foreach ($files as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
    }

    /**
     * 任务失败时的处理
     */
    public function failed(Exception $exception): void
    {
        Log::error('FFmpeg转换任务最终失败', [
            'task_id' => $this->task->id,
            'error' => $exception->getMessage()
        ]);

        $this->task->update([
            'status' => FileConversionTask::STATUS_FAILED,
            'error_message' => $exception->getMessage(),
            'completed_at' => now()
        ]);
    }
}
