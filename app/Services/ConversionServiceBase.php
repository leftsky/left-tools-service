<?php

namespace App\Services;

use App\Models\FileConversionTask;
use Exception;
use Illuminate\Support\Facades\Log;

/**
 * 转换服务基类
 * 
 * 提供转换服务的通用功能和接口定义
 * 所有具体的转换服务都应该继承此类
 */
abstract class ConversionServiceBase
{
    /**
     * 当前要处理的转换任务
     */
    protected ?FileConversionTask $task = null;

    /**
     * 服务配置
     */
    protected array $config = [];

    /**
     * 构造函数
     */
    public function __construct()
    {
        $this->config = $this->getDefaultConfig();
    }

    /**
     * 设置转换任务
     *
     * @param FileConversionTask $task
     * @return $this
     */
    public function setTask(FileConversionTask $task): self
    {
        $this->task = $task;
        return $this;
    }

    /**
     * 获取当前转换任务
     *
     * @return FileConversionTask|null
     */
    public function getTask(): ?FileConversionTask
    {
        return $this->task;
    }

    /**
     * 检查是否支持特定格式转换
     * 
     * 抽象方法，子类必须实现
     * 用于判断该服务是否支持从输入格式转换到输出格式
     *
     * @param string $inputFormat 输入格式（如：pdf, docx, mp4等）
     * @param string $outputFormat 输出格式（如：docx, pdf, mp4等）
     * @return bool 是否支持该转换
     */
    abstract public function supportsConversion(string $inputFormat, string $outputFormat): bool;

    /**
     * 提交转换任务
     * 
     * 抽象方法，子类必须实现
     * 用于向转换服务提交具体的转换任务
     *
     * @param FileConversionTask $task 转换任务
     * @return array 提交结果 ['success' => bool, 'message' => string, 'code' => int]
     */
    abstract public function submitConversionTask(FileConversionTask $task): array;

    /**
     * 验证转换选项
     * 
     * 子类可以重写此方法来实现特定的选项验证逻辑
     *
     * @param array $options 转换选项
     * @return bool 选项是否有效
     */
    public function validateConversionOptions(array $options): bool
    {
        // 默认实现：所有选项都有效
        // 子类可以重写此方法来实现特定的验证逻辑
        return true;
    }

    /**
     * 检查服务是否可用
     * 
     * 子类可以重写此方法来检查服务的可用性
     * 例如：检查API密钥、网络连接、本地工具等
     *
     * @return bool 服务是否可用
     */
    public function isAvailable(): bool
    {
        // 默认实现：服务可用
        // 子类应该重写此方法来检查具体的可用性条件
        return true;
    }

    /**
     * 获取服务名称
     * 
     * 子类可以重写此方法来返回具体的服务名称
     *
     * @return string 服务名称
     */
    public function getServiceName(): string
    {
        // 默认实现：返回类名
        $className = class_basename($this);
        return str_replace('Service', '', $className);
    }

    /**
     * 获取默认配置
     * 
     * 子类可以重写此方法来返回默认配置
     *
     * @return array 默认配置
     */
    protected function getDefaultConfig(): array
    {
        // 默认实现：返回空数组
        // 子类应该重写此方法来返回具体的配置
        return [];
    }

    /**
     * 构建成功响应
     *
     * @param array $data 响应数据
     * @param string $message 成功消息
     * @param int $code 响应代码
     * @return array
     */
    protected function buildSuccessResponse(array $data, string $message = '操作成功', int $code = 200): array
    {
        return [
            'success' => true,
            'data' => $data,
            'message' => $message,
            'code' => $code
        ];
    }

    /**
     * 构建错误响应
     *
     * @param string $message 错误消息
     * @param int $code 错误代码
     * @param array $data 额外数据
     * @return array
     */
    protected function buildErrorResponse(string $message, int $code = 500, array $data = []): array
    {
        return [
            'success' => false,
            'message' => $message,
            'code' => $code,
            'data' => $data
        ];
    }

    /**
     * 检查任务是否已设置
     *
     * @return bool
     */
    protected function hasTask(): bool
    {
        return $this->task !== null;
    }

    /**
     * 获取任务ID
     *
     * @return int|null
     */
    protected function getTaskId(): ?int
    {
        return $this->task?->id;
    }

    /**
     * 获取输入格式
     *
     * @return string|null
     */
    protected function getInputFormat(): ?string
    {
        return $this->task?->input_format;
    }

    /**
     * 获取输出格式
     *
     * @return string|null
     */
    protected function getOutputFormat(): ?string
    {
        return $this->task?->output_format;
    }

    private $tempInputFile = null;
    private $tempOutputFile = null;

    /**
     * 下载输入文件到临时目录
     *
     * @return string 临时文件路径
     */
    protected function downloadInputFile(): string
    {
        $task = $this->task;
        $tempDir = storage_path('app/temp');
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $inputExt = $task->input_format;
        $tempInputFile = $tempDir . '/input_' . $task->id . '_' . time() . '.' . $inputExt;

        // 根据输入方式下载文件
        switch ($task->input_method) {
            case FileConversionTask::INPUT_METHOD_URL:
                $this->downloadFromUrl($task->input_file, $tempInputFile);
                break;

            case FileConversionTask::INPUT_METHOD_UPLOAD:
            case FileConversionTask::INPUT_METHOD_DIRECT_UPLOAD:
                // 从存储中复制文件
                $content = \Illuminate\Support\Facades\Storage::get($task->input_file);
                file_put_contents($tempInputFile, $content);
                break;

            default:
                throw new Exception("不支持的输入方式: {$task->input_method}");
        }

        if (!file_exists($tempInputFile)) {
            throw new Exception('输入文件下载失败');
        }

        return $tempInputFile;
    }

    protected function getTempInputFile(): string
    {
        if (!$this->tempInputFile) {
            $this->tempInputFile = $this->downloadInputFile();
        }
        return $this->tempInputFile;
    }

    /**
     * 从URL下载文件
     *
     * @param string $url
     * @param string $targetPath
     */
    private function downloadFromUrl(string $url, string $targetPath): void
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
     * 上传输出文件到OSS
     *
     * @param string $outputFilePath
     * @return string 文件URL
     */
    protected function uploadOutputFile(string $outputFilePath): string
    {
        $this->tempOutputFile = $outputFilePath;
        $extension = pathinfo($outputFilePath, PATHINFO_EXTENSION);
        $randomNumber = rand(10000, 99999);
        $timestamp = date('Y-m-d H:i:s');
        $fileName = "格式转换大王 {$timestamp} {$randomNumber}.{$extension}";
        $folder = 'conversions';
        $filePath = $folder . '/' . $fileName;

        // 上传到OSS
        $disk = \Illuminate\Support\Facades\Storage::disk('oss');
        $content = file_get_contents($outputFilePath);
        $disk->put($filePath, $content);

        Log::info('文件上传到OSS完成', [
            'filename' => $fileName,
            'file_size' => strlen($content)
        ]);

        return \Illuminate\Support\Facades\Storage::url($filePath);
    }

    /**
     * 清理临时文件
     *
     * @param string ...$files
     */
    protected function cleanupFiles(): void
    {
        if ($this->tempInputFile && file_exists($this->tempInputFile)) {
            unlink($this->tempInputFile);
        }
        if ($this->tempOutputFile && file_exists($this->tempOutputFile)) {
            unlink($this->tempOutputFile);
        }
    }
}
