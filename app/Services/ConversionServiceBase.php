<?php

namespace App\Services;

use App\Models\FileConversionTask;
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
     * 获取支持的格式列表
     * 
     * 子类可以重写此方法来返回支持的格式信息
     *
     * @return array 支持的格式列表
     */
    public function getSupportedFormats(): array
    {
        // 默认实现：返回空数组
        // 子类应该重写此方法来返回具体的支持格式
        return [];
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
     * 记录日志
     *
     * @param string $level 日志级别
     * @param string $message 日志消息
     * @param array $context 上下文信息
     */
    protected function log(string $level, string $message, array $context = []): void
    {
        $context['service'] = $this->getServiceName();
        if ($this->task) {
            $context['task_id'] = $this->task->id;
        }

        Log::log($level, "[{$this->getServiceName()}] {$message}", $context);
    }

    /**
     * 记录信息日志
     *
     * @param string $message 日志消息
     * @param array $context 上下文信息
     */
    protected function logInfo(string $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }

    /**
     * 记录错误日志
     *
     * @param string $message 日志消息
     * @param array $context 上下文信息
     */
    protected function logError(string $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    /**
     * 记录警告日志
     *
     * @param string $message 日志消息
     * @param array $context 上下文信息
     */
    protected function logWarning(string $message, array $context = []): void
    {
        $this->log('warning', $message, $context);
    }

    /**
     * 记录调试日志
     *
     * @param string $message 日志消息
     * @param array $context 上下文信息
     */
    protected function logDebug(string $message, array $context = []): void
    {
        $this->log('debug', $message, $context);
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

    /**
     * 获取转换选项
     *
     * @return array
     */
    protected function getConversionOptions(): array
    {
        return $this->task?->getConversionOptions() ?? [];
    }
}
