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

class LibreOfficeConversionJob implements ShouldQueue
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
     * 支持的输入格式
     */
    const SUPPORTED_INPUT_FORMATS = [
        'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
        'odt', 'ods', 'odp', 'odg',
        'rtf', 'txt', 'csv', 'html', 'htm'
    ];

    /**
     * 支持的输出格式
     */
    const SUPPORTED_OUTPUT_FORMATS = [
        'pdf', 'docx', 'odt', 'xlsx', 'ods', 'pptx', 'odp',
        'txt', 'rtf', 'html', 'png', 'jpg'
    ];

    /**
     * 文件大小限制（50MB）
     */
    const MAX_FILE_SIZE = 50 * 1024 * 1024;

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
            Log::info('开始LibreOffice转换任务', [
                'task_id' => $this->task->id,
                'input_format' => $this->task->input_format,
                'output_format' => $this->task->output_format
            ]);

            // 更新任务状态为转换中
            $this->task->startProcessing();

            // 验证格式支持
            $this->validateFormats();

            // 下载输入文件
            $inputFilePath = $this->downloadInputFile();

            // 验证文件大小
            $fileSize = filesize($inputFilePath);
            if ($fileSize > self::MAX_FILE_SIZE) {
                throw new Exception("文件过大，请选择小于50MB的文件");
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

            Log::info('LibreOffice转换任务完成', [
                'task_id' => $this->task->id,
                'output_url' => $outputUrl,
                'output_size' => $outputSize
            ]);

        } catch (Exception $e) {
            Log::error('LibreOffice转换任务失败', [
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
     * 验证格式支持
     */
    protected function validateFormats(): void
    {
        if (!in_array($this->task->input_format, self::SUPPORTED_INPUT_FORMATS)) {
            throw new Exception("不支持的输入格式: {$this->task->input_format}");
        }

        if (!in_array($this->task->output_format, self::SUPPORTED_OUTPUT_FORMATS)) {
            throw new Exception("不支持的输出格式: {$this->task->output_format}");
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
        $tempInputFile = $tempDir . '/libreoffice_input_' . $this->task->id . '_' . time() . '.' . $inputExt;

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
                'user_agent' => 'Mozilla/5.0 (compatible; LibreOfficeConverter/1.0)'
            ]
        ]);

        $content = file_get_contents($url, false, $context);
        if ($content === false) {
            throw new Exception("无法从URL下载文件: {$url}");
        }

        file_put_contents($targetPath, $content);
    }

    /**
     * 执行LibreOffice转换
     */
    protected function performConversion(string $inputFilePath): string
    {
        $this->task->updateProgress(20);

        $tempDir = dirname($inputFilePath);
        $outputFormat = $this->task->output_format;
        $options = $this->task->getConversionOptions();

        // 特殊处理图片格式输出
        if (in_array($outputFormat, ['png', 'jpg'])) {
            return $this->convertToImage($inputFilePath, $tempDir, $outputFormat);
        }

        // 标准文档转换
        return $this->convertDocument($inputFilePath, $tempDir, $outputFormat, $options);
    }

    /**
     * 标准文档转换
     */
    protected function convertDocument(string $inputFilePath, string $tempDir, string $outputFormat, array $options): string
    {
        $this->task->updateProgress(30);

        // 构建LibreOffice转换命令
        $command = [
            'libreoffice',
            '--headless',
            '--invisible',
            '--nodefault',
            '--nolockcheck',
            '--nologo',
            '--norestore',
            '--convert-to', $outputFormat,
            '--outdir', $tempDir,
            $inputFilePath
        ];

        // 添加PDF特定选项
        if ($outputFormat === 'pdf') {
            // 可以添加PDF质量选项
            if (isset($options['pdf_quality'])) {
                // LibreOffice的PDF导出选项比较复杂，这里简化处理
            }
        }

        $this->task->updateProgress(50);

        // 执行转换命令
        $this->executeLibreOfficeCommand($command);

        $this->task->updateProgress(80);

        // 查找输出文件
        $inputBasename = pathinfo($inputFilePath, PATHINFO_FILENAME);
        $outputFilePath = $tempDir . '/' . $inputBasename . '.' . $outputFormat;

        if (!file_exists($outputFilePath)) {
            throw new Exception('转换失败，输出文件不存在');
        }

        return $outputFilePath;
    }

    /**
     * 转换为图片格式（首页预览）
     */
    protected function convertToImage(string $inputFilePath, string $tempDir, string $imageFormat): string
    {
        $this->task->updateProgress(30);

        // 首先转换为PDF
        $pdfCommand = [
            'libreoffice',
            '--headless',
            '--invisible',
            '--nodefault',
            '--nolockcheck',
            '--nologo',
            '--norestore',
            '--convert-to', 'pdf',
            '--outdir', $tempDir,
            $inputFilePath
        ];

        $this->executeLibreOfficeCommand($pdfCommand);

        $this->task->updateProgress(60);

        // 查找生成的PDF文件
        $inputBasename = pathinfo($inputFilePath, PATHINFO_FILENAME);
        $pdfFilePath = $tempDir . '/' . $inputBasename . '.pdf';

        if (!file_exists($pdfFilePath)) {
            throw new Exception('PDF转换失败');
        }

        // 使用ImageMagick或其他工具将PDF首页转换为图片
        $imageFilePath = $tempDir . '/' . $inputBasename . '.' . $imageFormat;
        
        // 检查是否有convert命令（ImageMagick）
        $convertAvailable = false;
        exec('which convert 2>/dev/null', $output, $returnCode);
        if ($returnCode === 0) {
            $convertAvailable = true;
        }

        if ($convertAvailable) {
            // 使用ImageMagick转换PDF首页为图片
            $convertCommand = [
                'convert',
                $pdfFilePath . '[0]', // 只转换第一页
                '-quality', '90',
                '-density', '150',
                $imageFilePath
            ];
            
            $this->executeCommand($convertCommand);
        } else {
            // 如果没有ImageMagick，返回PDF文件
            Log::warning('ImageMagick不可用，返回PDF文件而不是图片', [
                'task_id' => $this->task->id,
                'requested_format' => $imageFormat
            ]);
            return $pdfFilePath;
        }

        $this->task->updateProgress(90);

        // 清理临时PDF文件
        if (file_exists($pdfFilePath)) {
            unlink($pdfFilePath);
        }

        if (!file_exists($imageFilePath)) {
            throw new Exception('图片转换失败');
        }

        return $imageFilePath;
    }

    /**
     * 执行LibreOffice命令
     */
    protected function executeLibreOfficeCommand(array $command): void
    {
        $this->executeCommand($command);
    }

    /**
     * 执行系统命令
     */
    protected function executeCommand(array $command): void
    {
        $commandStr = implode(' ', array_map('escapeshellarg', $command));
        
        Log::info('执行LibreOffice命令', ['command' => $commandStr]);

        $descriptorspec = [
            0 => ['pipe', 'r'],  // stdin
            1 => ['pipe', 'w'],  // stdout
            2 => ['pipe', 'w']   // stderr
        ];

        $process = proc_open($commandStr, $descriptorspec, $pipes);

        if (!is_resource($process)) {
            throw new Exception('无法启动LibreOffice进程');
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
            Log::error('LibreOffice命令执行失败', [
                'command' => $commandStr,
                'return_code' => $returnCode,
                'stdout' => $stdout,
                'stderr' => $stderr
            ]);
            throw new Exception("LibreOffice转换失败: {$stderr}");
        }

        Log::info('LibreOffice命令执行成功', ['command' => $commandStr]);
    }

    /**
     * 上传输出文件到OSS
     */
    protected function uploadOutputFile(string $outputFilePath): string
    {
        $extension = pathinfo($outputFilePath, PATHINFO_EXTENSION);
        $randomNumber = rand(10000, 99999);
        $timestamp = date('Y-m-d H:i:s');
        $fileName = "文档转换 {$timestamp} {$randomNumber}.{$extension}";
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
        Log::error('LibreOffice转换任务最终失败', [
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
