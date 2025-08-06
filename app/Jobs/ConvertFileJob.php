<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ConvertFileJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600; // 10分钟超时
    public $tries = 3; // 重试3次
    public $maxFileSize = 104857600; // 100MB (100 * 1024 * 1024)

    protected $inputFilePath;
    protected $conversionParams;

    /**
     * 创建一个新的job实例
     *
     * @param string $inputFilePath 输入文件路径
     * @param array $conversionParams 转换参数，支持以下参数：
     *   - target_format: 目标格式 (如: mp4, avi, mov, mkv, webm, flv, mp3, wav, aac, ogg, flac)
     *   - resolution: 目标分辨率 (如: 1920x1080, 1280x720, 854x480)
     *   - bitrate: 视频比特率 (如: 2M, 1M, 500k)
     *   - framerate: 帧速率 (如: 30, 25, 60)
     *   - aspect_ratio: 宽高比 (如: 16:9, 4:3, 1:1)
     *   - rotation: 旋转角度 (如: 90, 180, 270)
     *   - audio_bitrate: 音频比特率 (如: 128k, 192k, 320k)
     *   - audio_sample_rate: 音频采样率 (如: 44100, 48000, 22050)
     *   - audio_pitch: 音频声调调整 (如: 1.0为原声, 1.2为升调, 0.8为降调)
     *   - mute: 是否静音 (true/false)
     */
    public function __construct($inputFilePath, $conversionParams = [])
    {
        $this->inputFilePath = $inputFilePath;
        $this->conversionParams = $conversionParams;
    }

    /**
     * 执行job
     */
    public function handle()
    {
        try {
            // 生成输出文件路径
            $outputFilePath = $this->generateOutputFilePath();
            
            Log::info('开始文件转换', [
                'input_file' => $this->inputFilePath,
                'output_file' => $outputFilePath,
                'params' => $this->conversionParams
            ]);

            // 检查输入文件是否存在
            if (!file_exists($this->inputFilePath)) {
                throw new \Exception("输入文件不存在: {$this->inputFilePath}");
            }

            // 检查文件大小
            $fileSize = filesize($this->inputFilePath);
            if ($fileSize > $this->maxFileSize) {
                throw new \Exception("文件大小超过限制: " . $this->formatFileSize($fileSize) . " > " . $this->formatFileSize($this->maxFileSize));
            }

            // 确保输出目录存在
            $outputDir = dirname($outputFilePath);
            if (!is_dir($outputDir)) {
                mkdir($outputDir, 0755, true);
            }

            // 构建ffmpeg命令
            $command = $this->buildFfmpegCommand($outputFilePath);

            // 执行转换
            $result = $this->executeFfmpeg($command);

            if ($result['success']) {
                Log::info('文件转换成功', [
                    'input_file' => $this->inputFilePath,
                    'output_file' => $outputFilePath,
                    'file_size' => filesize($outputFilePath)
                ]);

                // 这里可以添加转换成功后的处理逻辑
                // 比如更新数据库记录、发送通知等
                $this->onConversionSuccess($outputFilePath);
            } else {
                throw new \Exception("FFmpeg转换失败: " . $result['error']);
            }
        } catch (\Exception $e) {
            Log::error('文件转换失败', [
                'input_file' => $this->inputFilePath,
                'error' => $e->getMessage()
            ]);

            // 这里可以添加转换失败后的处理逻辑
            $this->onConversionFailed($e);

            throw $e;
        }
    }

    /**
     * 生成输出文件路径
     */
    protected function generateOutputFilePath()
    {
        $inputInfo = pathinfo($this->inputFilePath);
        $inputDir = $inputInfo['dirname'];
        $inputName = $inputInfo['filename'];
        
        // 获取目标格式，如果没有指定则保持原格式
        $targetFormat = $this->conversionParams['target_format'] ?? $inputInfo['extension'] ?? 'mp4';
        
        // 生成输出文件名
        $outputName = $inputName . '_converted_' . date('YmdHis');
        $outputPath = $inputDir . '/' . $outputName . '.' . $targetFormat;
        
        return $outputPath;
    }

    /**
     * 构建ffmpeg命令
     */
    protected function buildFfmpegCommand($outputFilePath)
    {
        $baseCommand = "ffmpeg -i " . escapeshellarg($this->inputFilePath);

        // 构建转换参数
        $ffmpegParams = $this->buildFfmpegParams();

        // 添加转换参数
        foreach ($ffmpegParams as $param => $value) {
            if (is_string($value)) {
                $baseCommand .= " -{$param} " . escapeshellarg($value);
            } else {
                $baseCommand .= " -{$param} {$value}";
            }
        }

        // 添加输出文件
        $baseCommand .= " " . escapeshellarg($outputFilePath);

        // 添加静默输出参数
        $baseCommand .= " -y -loglevel error";

        return $baseCommand;
    }

    /**
     * 构建ffmpeg参数
     */
    protected function buildFfmpegParams()
    {
        $params = [];
        
        // 视频参数
        if (isset($this->conversionParams['target_format'])) {
            $params['f'] = $this->conversionParams['target_format'];
        }
        
        if (isset($this->conversionParams['resolution'])) {
            $params['vf'] = 'scale=' . $this->conversionParams['resolution'];
        }
        
        if (isset($this->conversionParams['bitrate'])) {
            $params['b:v'] = $this->conversionParams['bitrate'];
        }
        
        if (isset($this->conversionParams['framerate'])) {
            $params['r'] = $this->conversionParams['framerate'];
        }
        
        if (isset($this->conversionParams['aspect_ratio'])) {
            $params['aspect'] = $this->conversionParams['aspect_ratio'];
        }
        
        if (isset($this->conversionParams['rotation'])) {
            $params['vf'] = isset($params['vf']) ? $params['vf'] . ',rotate=' . $this->conversionParams['rotation'] : 'rotate=' . $this->conversionParams['rotation'];
        }
        
        // 音频参数
        if (isset($this->conversionParams['audio_bitrate'])) {
            $params['b:a'] = $this->conversionParams['audio_bitrate'];
        }
        
        if (isset($this->conversionParams['audio_sample_rate'])) {
            $params['ar'] = $this->conversionParams['audio_sample_rate'];
        }
        
        if (isset($this->conversionParams['audio_pitch'])) {
            $params['af'] = 'asetrate=' . $this->conversionParams['audio_pitch'];
        }
        
        if (isset($this->conversionParams['mute']) && $this->conversionParams['mute']) {
            $params['an'] = ''; // 静音
        }
        
        return $params;
    }

    /**
     * 格式化文件大小
     */
    protected function formatFileSize($bytes)
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * 执行ffmpeg命令
     */
    protected function executeFfmpeg($command)
    {
        $output = [];
        $returnCode = 0;

        exec($command . " 2>&1", $output, $returnCode);

        return [
            'success' => $returnCode === 0,
            'output' => $output,
            'error' => $returnCode !== 0 ? implode("\n", $output) : null
        ];
    }

    /**
     * 转换成功后的处理
     */
    protected function onConversionSuccess($outputFilePath)
    {
        // 可以在这里添加转换成功后的逻辑
        // 比如更新数据库记录、发送通知等
        Log::info('文件转换完成，输出文件: ' . $outputFilePath);
    }

    /**
     * 转换失败后的处理
     */
    protected function onConversionFailed(\Exception $e)
    {
        // 可以在这里添加转换失败后的逻辑
        // 比如清理临时文件、发送失败通知等
        Log::error('文件转换失败: ' . $e->getMessage());
    }

    /**
     * Job失败时的处理
     */
    public function failed(\Throwable $exception)
    {
        Log::error('ConvertFileJob 最终失败', [
            'input_file' => $this->inputFilePath,
            'error' => $exception->getMessage()
        ]);

        // 这里可以添加最终失败后的处理逻辑
        // 比如发送失败通知、清理资源等
    }
}
