<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Log;
use App\Services\CloudConvertService;
use App\Services\ConvertioService;

class FileConversionTask extends Model
{
    use HasFactory;

    /**
     * 任务状态常量
     */
    const STATUS_WAIT = 0;         // 等待中
    const STATUS_CONVERT = 1;      // 转换中
    const STATUS_FINISH = 2;       // 已完成
    const STATUS_FAILED = 3;       // 失败
    const STATUS_CANCELLED = 4;    // 已取消

    /**
     * 状态映射
     */
    const STATUS_MAP = [
        self::STATUS_WAIT => '等待中',
        self::STATUS_CONVERT => '转换中',
        self::STATUS_FINISH => '已完成',
        self::STATUS_FAILED => '失败',
        self::STATUS_CANCELLED => '已取消',
    ];

    /**
     * 转换引擎常量
     */
    const ENGINE_CONVERTIO = 'convertio';
    const ENGINE_CLOUDCONVERT = 'cloudconvert';

    /**
     * 输入方式常量
     */
    const INPUT_METHOD_URL = 'url';
    const INPUT_METHOD_RAW = 'raw';
    const INPUT_METHOD_BASE64 = 'base64';
    const INPUT_METHOD_UPLOAD = 'upload';
    const INPUT_METHOD_DIRECT_UPLOAD = 'direct_upload';

    /**
     * 可批量赋值的属性
     */
    protected $fillable = [
        'user_id',
        'convertio_id',
        'cloudconvert_id',
        'conversion_engine',
        'input_method',
        'input_file',
        'filename',
        'input_format',
        'file_size',
        'output_format',
        'conversion_options',
        'status',
        'step_percent',
        'processing_time',
        'output_url',
        'output_size',
        'output_files',
        'callback_url',
        'error_message',
        'tag',
        'started_at',
        'completed_at',
    ];

    /**
     * 属性转换
     */
    protected $casts = [
        'conversion_options' => 'array',
        'output_files' => 'array',
        'file_size' => 'integer',
        'output_size' => 'integer',
        'step_percent' => 'integer',
        'processing_time' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'status' => 'integer',
    ];

    /**
     * 用户关联
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 获取状态文本
     */
    public function getStatusTextAttribute(): string
    {
        return self::STATUS_MAP[$this->status] ?? '未知状态';
    }

    /**
     * 获取格式化的文件大小
     */
    public function getFormattedFileSizeAttribute(): string
    {
        return $this->file_size ? $this->formatFileSize($this->file_size) : '未知';
    }

    /**
     * 获取格式化的输出文件大小
     */
    public function getFormattedOutputSizeAttribute(): string
    {
        return $this->output_size ? $this->formatFileSize($this->output_size) : '未知';
    }

    /**
     * 获取格式化的处理时间
     */
    public function getFormattedProcessingTimeAttribute(): string
    {
        if (!$this->processing_time) {
            return '未知';
        }

        $seconds = $this->processing_time;
        if ($seconds < 60) {
            return $seconds . '秒';
        } elseif ($seconds < 3600) {
            return round($seconds / 60, 1) . '分钟';
        } else {
            return round($seconds / 3600, 1) . '小时';
        }
    }

    /**
     * 检查任务是否已完成
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_FINISH;
    }

    /**
     * 检查任务是否失败
     */
    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    /**
     * 检查任务是否已取消
     */
    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    /**
     * 检查任务是否正在处理
     */
    public function isProcessing(): bool
    {
        return $this->status === self::STATUS_CONVERT;
    }

    /**
     * 检查任务是否等待中
     */
    public function isWaiting(): bool
    {
        return $this->status === self::STATUS_WAIT;
    }

    /**
     * 检查任务是否可取消
     */
    public function canBeCancelled(): bool
    {
        return in_array($this->status, [self::STATUS_WAIT, self::STATUS_CONVERT]);
    }

    /**
     * 检查是否使用 Convertio 引擎
     */
    public function isConvertioEngine(): bool
    {
        return $this->conversion_engine === self::ENGINE_CONVERTIO;
    }

    /**
     * 检查是否使用 CloudConvert 引擎
     */
    public function isCloudConvertEngine(): bool
    {
        return $this->conversion_engine === self::ENGINE_CLOUDCONVERT;
    }

    /**
     * 开始处理任务
     */
    public function startProcessing(): void
    {
        $this->update([
            'status' => self::STATUS_CONVERT,
            'started_at' => now(),
            'step_percent' => 0,
        ]);
    }

    /**
     * 更新转换进度
     */
    public function updateProgress(int $stepPercent): void
    {
        $this->update([
            'step_percent' => $stepPercent,
        ]);
    }

    /**
     * 完成任务
     */
    public function complete(string $outputUrl, int $outputSize): void
    {
        $completedAt = now();

        // 自动计算处理时间（完成时间 - 创建时间）
        $processingTime = $this->created_at->diffInSeconds($completedAt);

        Log::info('complete', [$outputUrl, $outputSize, $processingTime]);
        $this->update([
            'status' => self::STATUS_FINISH,
            'output_url' => $outputUrl,
            'output_size' => $outputSize,
            'processing_time' => $processingTime,
            'output_files' => null,
            'step_percent' => 100,
            'completed_at' => $completedAt,
        ]);
    }

    /**
     * 标记任务失败
     */
    public function markAsFailed(?string $errorMessage = null): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'error_message' => $errorMessage,
            'completed_at' => now(),
        ]);
    }

    /**
     * 标记任务取消
     */
    public function markAsCancelled(): void
    {
        $this->update([
            'status' => self::STATUS_CANCELLED,
            'completed_at' => now(),
        ]);
    }

    /**
     * 设置 Convertio ID
     */
    public function setConvertioId(string $convertioId): void
    {
        $this->update([
            'convertio_id' => $convertioId,
            'conversion_engine' => self::ENGINE_CONVERTIO,
        ]);
    }

    /**
     * 设置 CloudConvert ID
     */
    public function setCloudConvertId(string $cloudconvertId): void
    {
        $this->update([
            'cloudconvert_id' => $cloudconvertId,
            'conversion_engine' => self::ENGINE_CLOUDCONVERT,
        ]);
    }

    /**
     * 获取转换选项
     */
    public function getConversionOptions(): array
    {
        return $this->conversion_options ?? [];
    }

    /**
     * 获取特定转换选项
     */
    public function getConversionOption(string $key, $default = null)
    {
        return $this->conversion_options[$key] ?? $default;
    }

    /**
     * 获取OCR设置
     */
    public function getOcrSettings(): ?array
    {
        return $this->conversion_options['ocr_settings'] ?? null;
    }

    /**
     * 检查是否启用OCR
     */
    public function isOcrEnabled(): bool
    {
        return $this->conversion_options['ocr_enabled'] ?? false;
    }

    /**
     * 获取回调URL
     */
    public function getCallbackUrl(): ?string
    {
        return $this->callback_url;
    }

    /**
     * 获取输出文件列表
     */
    public function getOutputFiles(): array
    {
        return $this->output_files ?? [];
    }

    /**
     * 格式化文件大小
     */
    protected function formatFileSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * 作用域：按状态筛选
     */
    public function scopeByStatus($query, int $status)
    {
        return $query->where('status', $status);
    }

    /**
     * 作用域：按用户筛选
     */
    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * 作用域：按转换引擎筛选
     */
    public function scopeByEngine($query, string $engine)
    {
        return $query->where('conversion_engine', $engine);
    }

    /**
     * 作用域：按 Convertio ID 筛选
     */
    public function scopeByConvertioId($query, string $convertioId)
    {
        return $query->where('convertio_id', $convertioId);
    }

    /**
     * 作用域：按 CloudConvert ID 筛选
     */
    public function scopeByCloudConvertId($query, string $cloudconvertId)
    {
        return $query->where('cloudconvert_id', $cloudconvertId);
    }

    /**
     * 作用域：按输入方式筛选
     */
    public function scopeByInputMethod($query, string $inputMethod)
    {
        return $query->where('input_method', $inputMethod);
    }

    /**
     * 作用域：按输入格式筛选
     */
    public function scopeByInputFormat($query, string $inputFormat)
    {
        return $query->where('input_format', $inputFormat);
    }

    /**
     * 作用域：按输出格式筛选
     */
    public function scopeByOutputFormat($query, string $outputFormat)
    {
        return $query->where('output_format', $outputFormat);
    }

    /**
     * 作用域：按标签筛选
     */
    public function scopeByTag($query, string $tag)
    {
        return $query->where('tag', $tag);
    }

    /**
     * 作用域：最近的任务
     */
    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * 作用域：等待中的任务
     */
    public function scopeWaiting($query)
    {
        return $query->where('status', self::STATUS_WAIT);
    }

    /**
     * 作用域：转换中的任务
     */
    public function scopeConverting($query)
    {
        return $query->where('status', self::STATUS_CONVERT);
    }

    /**
     * 作用域：已完成的任务
     */
    public function scopeFinished($query)
    {
        return $query->where('status', self::STATUS_FINISH);
    }

    /**
     * 作用域：失败的任务
     */
    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    /**
     * 作用域：已取消的任务
     */
    public function scopeCancelled($query)
    {
        return $query->where('status', self::STATUS_CANCELLED);
    }

    /**
     * 作用域：有 Convertio ID 的任务
     */
    public function scopeWithConvertioId($query)
    {
        return $query->whereNotNull('convertio_id');
    }

    /**
     * 作用域：有 CloudConvert ID 的任务
     */
    public function scopeWithCloudConvertId($query)
    {
        return $query->whereNotNull('cloudconvert_id');
    }

    /**
     * 作用域：启用OCR的任务
     */
    public function scopeWithOcr($query)
    {
        return $query->whereJsonContains('conversion_options->ocr_enabled', true);
    }

    /**
     * 作用域：有回调URL的任务
     */
    public function scopeWithCallback($query)
    {
        return $query->whereNotNull('callback_url');
    }

    /**
     * 作用域：有标签的任务
     */
    public function scopeWithTag($query)
    {
        return $query->whereNotNull('tag');
    }

    /**
     * 从转换引擎更新任务状态
     */
    public function updateStatusFromEngine(): void
    {
        try {
            // 如果任务已经完成或失败，不需要再查询
            if ($this->isCompleted() || $this->isFailed() || $this->isCancelled()) {
                return;
            }

            // 根据引擎类型查询状态
            if ($this->isCloudConvertEngine() && $this->cloudconvert_id) {
                $this->updateCloudConvertStatus();
            } elseif ($this->isConvertioEngine() && $this->convertio_id) {
                $this->updateConvertioStatus();
            }
        } catch (\Exception $e) {
            Log::error('更新任务状态失败', [
                'task_id' => $this->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * 更新 CloudConvert 任务状态
     */
    protected function updateCloudConvertStatus(): void
    {
        try {
            $cloudConvertService = app(CloudConvertService::class);
            $result = $cloudConvertService->getStatus($this->cloudconvert_id);

            if ($result['success']) {
                $data = $result['data'];
                $status = $data['status'] ?? null;
                $progress = $data['progress'] ?? 0;

                // 更新任务进度
                $this->updateProgress($progress);

                if ($status === 'finished') {
                    $export = $data['tasks']['export'] ?? null;
                    // 直接获取输出文件信息并完成任务
                    $files = $export['result']->files ?? [];
                    if (!empty($files)) {
                        $outputFile = $files[0];
                        $outputUrl = $outputFile->url ?? null;
                        $outputSize = $outputFile->size ?? 0;

                        if ($outputUrl) {
                            $this->complete($outputUrl, $outputSize);
                        }
                    }
                } elseif ($status === 'error') {
                    $errorMessage = $data['error'] ?? '转换失败';
                    Log::error('CloudConvert 任务失败', [
                        'task_id' => $this->id,
                        'cloudconvert_id' => $this->cloudconvert_id,
                        'error' => $errorMessage
                    ]);
                    $this->markAsFailed($errorMessage);
                } elseif ($status === 'processing') {
                    // 添加处理中状态的日志
                    Log::info('CloudConvert 任务处理中', [
                        'task_id' => $this->id,
                        'cloudconvert_id' => $this->cloudconvert_id,
                        'progress' => $progress
                    ]);
                }
            } else {
                Log::error('获取 CloudConvert 状态失败', [
                    'task_id' => $this->id,
                    'cloudconvert_id' => $this->cloudconvert_id,
                    'result' => $result
                ]);
            }
        } catch (\Exception $e) {
            Log::error('更新 CloudConvert 状态失败', [
                'task_id' => $this->id,
                'cloudconvert_id' => $this->cloudconvert_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * 更新 Convertio 任务状态
     */
    protected function updateConvertioStatus(): void
    {
        try {
            $convertioService = app(ConvertioService::class);
            $result = $convertioService->getStatus($this->convertio_id);

            if ($result['success']) {
                $data = $result['data'];
                // 确保数据是对象，如果不是则转换为对象
                if (is_array($data)) {
                    $data = (object) $data;
                }

                $step = $data->step ?? null;
                $progress = $data->step_percent ?? 0;

                // 更新任务进度
                $this->updateProgress($progress);

                Log::info('Convertio 状态', [$data]);

                // Convertio 的状态处理
                if ($step === 'finish') {
                    // 任务完成，获取下载链接
                    $downloadResult = $convertioService->downloadResult($this->convertio_id);
                    if ($downloadResult['success']) {
                        $downloadData = $downloadResult['data'];
                        $outputUrl = $downloadData['download_url'] ?? null;
                        $outputSize = $downloadData['size'] ?? 0;

                        if ($outputUrl) {
                            $this->complete($outputUrl, $outputSize);
                        }
                    }
                } elseif ($step === 'error') {
                    $errorMessage = $data->error ?? '转换失败';
                    Log::error('Convertio 任务失败', [
                        'task_id' => $this->id,
                        'convertio_id' => $this->convertio_id,
                        'error' => $errorMessage
                    ]);
                    $this->markAsFailed($errorMessage);
                } elseif ($step === 'convert') {
                    // 添加转换中状态的日志
                    Log::info('Convertio 任务转换中', [
                        'task_id' => $this->id,
                        'convertio_id' => $this->convertio_id,
                        'progress' => $progress
                    ]);
                }
            } else {
                Log::error('获取 Convertio 状态失败', [
                    'task_id' => $this->id,
                    'convertio_id' => $this->convertio_id,
                    'result' => $result
                ]);
            }
        } catch (\Exception $e) {
            Log::error('更新 Convertio 状态失败', [
                'task_id' => $this->id,
                'convertio_id' => $this->convertio_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}
