<?php

namespace App\Services;

use App\Models\FileConversionTask;
use Illuminate\Support\Facades\Log;
use Exception;

class ImageMagickService extends ConversionServiceBase
{


    /**
     * 转换选项配置
     */
    const CONVERSION_OPTIONS = [
        // 基础选项
        'quality' => ['type' => 'range', 'min' => 1, 'max' => 100, 'default' => 85],
        'width' => ['type' => 'number', 'min' => 1, 'max' => 20000, 'default' => null],
        'height' => ['type' => 'number', 'min' => 1, 'max' => 20000, 'default' => null],

        // 调整选项
        'resize_mode' => ['type' => 'select', 'options' => ['fit', 'crop', 'stretch', 'fill'], 'default' => 'fit'],
        'maintain_aspect' => ['type' => 'boolean', 'default' => true],
        'background_color' => ['type' => 'color', 'default' => '#FFFFFF'],

        // 质量选项
        'compression' => ['type' => 'select', 'options' => ['none', 'fast', 'good', 'best'], 'default' => 'good'],
        'dither' => ['type' => 'boolean', 'default' => false],
        'strip' => ['type' => 'boolean', 'default' => false], // 移除元数据

        // 格式特定选项
        'format_specific' => ['type' => 'object', 'default' => []]
    ];

    /**
     * 文件大小限制（100MB）
     */
    const MAX_FILE_SIZE = 100 * 1024 * 1024;

    /**
     * 检查是否支持特定格式转换
     *
     * @param string $inputFormat 输入格式
     * @param string $outputFormat 输出格式
     * @return bool 是否支持该转换
     */
    public function supportsConversion(string $inputFormat, string $outputFormat): bool
    {
        // 检查ImageMagick是否可用
        if (!$this->isImageMagickAvailable()) {
            return false;
        }

        // 使用动态检测检查格式支持
        $formatSupport = $this->checkImageMagickFormatSupport($inputFormat, $outputFormat);
        return $formatSupport['supports_conversion'];
    }

    /**
     * 检测ImageMagick支持的格式
     * 
     * @param string|null $inputFormat 输入格式（可选）
     * @param string|null $outputFormat 输出格式（可选）
     * @return array 返回格式支持信息
     */
    public function checkImageMagickFormatSupport(?string $inputFormat = null, ?string $outputFormat = null): array
    {
        // 检查ImageMagick是否可用
        if (!$this->isImageMagickAvailable()) {
            return [
                'available' => false,
                'error' => 'ImageMagick不可用',
                'supports_conversion' => false
            ];
        }

        Log::info('ImageMagick格式支持检查', [
            'inputFormat' => $inputFormat,
            'outputFormat' => $outputFormat
        ]);

        try {
            $supportsConversion = false;

            if ($inputFormat && $outputFormat) {
                // 直接检查输入格式是否支持读取（匹配格式名，包括可能的*号）
                $inputCheck = shell_exec("convert -list format | awk -v format='{$inputFormat}' 'tolower(gensub(/\\*/, \"\", \"g\", \$1)) == tolower(format) {print \$0}' 2>&1");
                $inputSupported = !empty($inputCheck) && strpos($inputCheck, 'r') !== false;

                // 直接检查输出格式是否支持写入（匹配格式名，包括可能的*号）
                $outputCheck = shell_exec("convert -list format | awk -v format='{$outputFormat}' 'tolower(gensub(/\\*/, \"\", \"g\", \$1)) == tolower(format) {print \$0}' 2>&1");
                $outputSupported = !empty($outputCheck) && strpos($outputCheck, 'w') !== false;

                $supportsConversion = $inputSupported && $outputSupported;

                // 总是记录检查结果，方便调试
                Log::info('ImageMagick格式支持检查结果', [
                    'inputFormat' => $inputFormat,
                    'outputFormat' => $outputFormat,
                    'inputSupported' => $inputSupported,
                    'outputSupported' => $outputSupported,
                    'supportsConversion' => $supportsConversion,
                    'inputCheck' => $inputCheck ?: 'NOT_FOUND',
                    'outputCheck' => $outputCheck ?: 'NOT_FOUND'
                ]);
            }

            return [
                'available' => true,
                'supports_conversion' => $supportsConversion
            ];
        } catch (Exception $e) {
            Log::error('检测ImageMagick格式支持时出错', [
                'error' => $e->getMessage(),
                'input_format' => $inputFormat,
                'output_format' => $outputFormat
            ]);

            return [
                'available' => false,
                'error' => $e->getMessage(),
                'supports_conversion' => false
            ];
        }
    }

    /**
     * 提交转换任务
     *
     * @param FileConversionTask $task
     * @return array 提交结果
     */
    public function submitConversionTask(FileConversionTask $task): array
    {
        try {
            // 设置任务
            $this->setTask($task);

            // 验证转换选项
            if (!$this->validateConversionOptions($task->getConversionOptions())) {
                return $this->buildErrorResponse('转换选项验证失败', 400);
            }

            // 执行转换任务
            $result = $this->executeConversion($task);

            return $result;
        } catch (Exception $e) {
            Log::error('ImageMagick转换任务提交失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->buildErrorResponse('ImageMagick转换任务提交失败: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 执行ImageMagick转换任务
     *
     * @param FileConversionTask $task
     * @return array 转换结果
     */
    public function executeConversion(FileConversionTask $task): array
    {
        try {
            Log::info('开始ImageMagick转换任务', [
                'task_id' => $task->id,
                'input_format' => $task->input_format,
                'output_format' => $task->output_format
            ]);

            // 更新任务状态为转换中
            $task->startProcessing();

            // 验证格式支持
            $this->validateFormats($task);

            // 下载输入文件
            $inputFilePath = $this->downloadInputFile();

            // 验证文件大小
            $fileSize = filesize($inputFilePath);
            if ($fileSize > self::MAX_FILE_SIZE) {
                throw new Exception("文件过大，请选择小于100MB的文件");
            }

            // 执行转换
            $outputFilePath = $this->performConversion($task, $inputFilePath);

            // 上传结果文件
            $outputUrl = $this->uploadOutputFile($outputFilePath);

            // 更新任务状态为完成
            $outputSize = filesize($outputFilePath);
            $task->update([
                'status' => FileConversionTask::STATUS_FINISH,
                'output_url' => $outputUrl,
                'output_size' => $outputSize,
                'step_percent' => 100,
                'completed_at' => now(),
                'processing_time' => $task->started_at->diffInSeconds(now())
            ]);

            // 清理临时文件
            $this->cleanupFiles();

            Log::info('ImageMagick转换任务完成', [
                'task_id' => $task->id,
                'output_url' => $outputUrl,
                'output_size' => $outputSize
            ]);

            return $this->buildSuccessResponse([
                'task_id' => $task->id,
                'status' => 'finished',
                'output_url' => $outputUrl,
                'output_size' => $outputSize
            ], 'ImageMagick转换任务完成');
        } catch (Exception $e) {
            Log::error('ImageMagick转换任务失败', [
                'task_id' => $task->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // 更新任务状态为失败
            $task->update([
                'status' => FileConversionTask::STATUS_FAILED,
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
                'processing_time' => $task->started_at->diffInSeconds(now())
            ]);

            // 清理可能的临时文件
            if (isset($inputFilePath) && file_exists($inputFilePath)) {
                unlink($inputFilePath);
            }
            if (isset($outputFilePath) && file_exists($outputFilePath)) {
                unlink($outputFilePath);
            }

            return $this->buildErrorResponse('ImageMagick转换任务失败: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 重写服务名称
     *
     * @return string
     */
    public function getServiceName(): string
    {
        return 'imagemagick';
    }

    /**
     * 重写服务可用性检查
     *
     * @return bool
     */
    public function isAvailable(): bool
    {
        return $this->isImageMagickAvailable();
    }

    /**
     * 重写支持的格式列表
     *
     * @return array
     */
    public function getSupportedFormats(): array
    {
        // 如果需要获取所有支持的格式，可以调用一次完整的检测
        $command = ['convert', '-list', 'format'];
        $output = shell_exec(implode(' ', $command) . ' 2>&1');

        $inputFormats = [];
        $outputFormats = [];

        if ($output) {
            $lines = explode("\n", trim($output));
            foreach ($lines as $line) {
                $line = trim($line);
                if (preg_match('/^(\w+)\s+([rwm]+)\s+(.+)$/', $line, $matches)) {
                    $format = strtolower($matches[1]);
                    $modes = $matches[2];

                    if (strpos($modes, 'r') !== false) {
                        $inputFormats[] = $format;
                    }
                    if (strpos($modes, 'w') !== false) {
                        $outputFormats[] = $format;
                    }
                }
            }
        }

        return [
            'input_formats' => array_unique($inputFormats),
            'output_formats' => array_unique($outputFormats),
            'conversion_options' => self::CONVERSION_OPTIONS,
            'max_file_size' => self::MAX_FILE_SIZE
        ];
    }

    /**
     * 获取支持的配置选项（保持向后兼容）
     *
     * @return array
     * @deprecated 使用 getSupportedFormats() 方法替代
     */
    public function getSupportedConfigs(): array
    {
        $formats = $this->getSupportedFormats();
        return [
            'inputFormats' => $formats['input_formats'],
            'outputFormats' => $formats['output_formats'],
            'conversionOptions' => self::CONVERSION_OPTIONS,
            'maxFileSize' => self::MAX_FILE_SIZE
        ];
    }

    /**
     * 验证转换选项
     *
     * @param array $options
     * @return bool
     */
    public function validateConversionOptions(array $options): bool
    {
        // 验证质量参数
        if (isset($options['quality'])) {
            $quality = (int) $options['quality'];
            if ($quality < 1 || $quality > 100) {
                return false;
            }
        }

        // 验证尺寸参数
        if (isset($options['width'])) {
            $width = (int) $options['width'];
            if ($width < 1 || $width > 20000) {
                return false;
            }
        }

        if (isset($options['height'])) {
            $height = (int) $options['height'];
            if ($height < 1 || $height > 20000) {
                return false;
            }
        }

        return true;
    }

    /**
     * 检查ImageMagick是否可用
     *
     * @return bool
     */
    public function isImageMagickAvailable(): bool
    {
        try {
            $output = [];
            $returnCode = 0;
            exec('convert --version 2>&1', $output, $returnCode);
            return $returnCode === 0;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 获取ImageMagick版本信息
     *
     * @return string|null
     */
    public function getImageMagickVersion(): ?string
    {
        try {
            $output = [];
            exec('convert --version 2>&1', $output);
            if (!empty($output)) {
                // 解析版本信息
                foreach ($output as $line) {
                    if (preg_match('/Version: ImageMagick\s+([^\s]+)/', $line, $matches)) {
                        return $matches[1];
                    }
                }
            }
            return null;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * 判断是否应该使用ImageMagick进行转换
     *
     * @param string $inputFormat
     * @param string $outputFormat
     * @return bool
     */
    public function shouldUseImageMagick(string $inputFormat, string $outputFormat): bool
    {
        // 检查ImageMagick是否可用
        if (!$this->isImageMagickAvailable()) {
            Log::info('ImageMagick不可用', [
                'input_format' => $inputFormat,
                'output_format' => $outputFormat
            ]);
            return false;
        }

        // 使用动态检测检查格式支持
        $formatSupport = $this->checkImageMagickFormatSupport($inputFormat, $outputFormat);
        if (!$formatSupport['supports_conversion']) {
            return false;
        }

        // 检查转换的合理性
        if (!$this->isValidConversion($inputFormat, $outputFormat)) {
            return false;
        }

        Log::info('使用ImageMagick进行转换', [
            'input_format' => $inputFormat,
            'output_format' => $outputFormat
        ]);
        return true;
    }

    /**
     * 验证转换的合理性
     *
     * @param string $inputFormat
     * @param string $outputFormat
     * @return bool
     */
    public function isValidConversion(string $inputFormat, string $outputFormat): bool
    {
        // 特殊规则：某些格式转换需要特殊处理
        $specialConversions = [
            'psd' => ['png', 'jpg', 'jpeg', 'tiff', 'webp', 'pdf'],
            'ai' => ['png', 'jpg', 'jpeg', 'tiff', 'webp', 'pdf'],
            'eps' => ['png', 'jpg', 'jpeg', 'tiff', 'webp', 'pdf'],
            'svg' => ['png', 'jpg', 'jpeg', 'tiff', 'webp', 'pdf'],
            'pdf' => ['png', 'jpg', 'jpeg', 'tiff', 'webp', 'gif'],
            'raw' => ['png', 'jpg', 'jpeg', 'tiff', 'webp'],
            'heic' => ['png', 'jpg', 'jpeg', 'tiff', 'webp'],
            'heif' => ['png', 'jpg', 'jpeg', 'tiff', 'webp']
        ];

        // 检查特殊转换规则
        if (isset($specialConversions[$inputFormat])) {
            return in_array($outputFormat, $specialConversions[$inputFormat]);
        }

        // 通用规则：大多数格式之间都可以转换
        return true;
    }

    /**
     * 获取ImageMagick转换命令
     *
     * @param string $inputFile
     * @param string $outputFile
     * @param string $outputFormat
     * @param array $options
     * @return array
     */
    public function getConversionCommand(string $inputFile, string $outputFile, string $outputFormat, array $options = []): array
    {
        $command = ['convert'];

        // 添加输入文件
        $command[] = $inputFile;

        // 添加转换选项
        $command = array_merge($command, $this->buildConversionOptions($options, $outputFormat));

        // 添加输出文件
        $command[] = $outputFile;

        return $command;
    }

    /**
     * 验证格式支持
     */
    protected function validateFormats(FileConversionTask $task): void
    {
        $formatSupport = $this->checkImageMagickFormatSupport($task->input_format, $task->output_format);
        if (!$formatSupport['supports_conversion']) {
            throw new Exception("不支持的格式转换: {$task->input_format} -> {$task->output_format}");
        }
    }

    /**
     * 执行ImageMagick转换
     */
    protected function performConversion(FileConversionTask $task, string $inputFilePath): string
    {
        $task->updateProgress(20);

        $tempDir = dirname($inputFilePath);
        $outputFormat = $task->output_format;
        $options = $task->getConversionOptions();

        // 构建转换命令
        $command = $this->buildConvertCommand($inputFilePath, $tempDir, $outputFormat, $options);

        $task->updateProgress(50);

        // 执行转换命令
        $this->executeImageMagickCommand($command);

        $task->updateProgress(80);

        // 查找输出文件
        $inputBasename = pathinfo($inputFilePath, PATHINFO_FILENAME);
        $outputFilePath = $tempDir . '/' . $inputBasename . '.' . $outputFormat;

        if (!file_exists($outputFilePath)) {
            throw new Exception('转换失败，输出文件不存在');
        }

        return $outputFilePath;
    }

    /**
     * 构建ImageMagick转换命令
     */
    protected function buildConvertCommand(string $inputFile, string $tempDir, string $outputFormat, array $options): array
    {
        $command = ['convert'];

        // 添加输入文件
        $command[] = $inputFile;

        // 添加转换选项
        $command = array_merge($command, $this->buildConversionOptions($options, $outputFormat));

        // 添加输出文件
        $inputBasename = pathinfo($inputFile, PATHINFO_FILENAME);
        $outputFile = $tempDir . '/' . $inputBasename . '.' . $outputFormat;
        $command[] = $outputFile;

        return $command;
    }

    /**
     * 构建转换选项
     */
    protected function buildConversionOptions(array $options, string $outputFormat): array
    {
        $conversionOptions = [];

        // 质量设置
        if (isset($options['quality'])) {
            $quality = (int) $options['quality'];
            if ($quality >= 1 && $quality <= 100) {
                $conversionOptions[] = '-quality';
                $conversionOptions[] = (string) $quality;
            }
        }

        // 尺寸调整
        if (isset($options['width']) || isset($options['height'])) {
            $resizeParams = $this->buildResizeParams($options);
            if (!empty($resizeParams)) {
                $conversionOptions = array_merge($conversionOptions, $resizeParams);
            }
        }

        // 背景颜色设置
        if (isset($options['background_color'])) {
            $conversionOptions[] = '-background';
            $conversionOptions[] = $options['background_color'];
        }

        // 移除元数据
        if (isset($options['strip']) && $options['strip']) {
            $conversionOptions[] = '-strip';
        }

        // 格式特定选项
        $formatSpecificOptions = $this->getFormatSpecificOptions($outputFormat, $options);
        if (!empty($formatSpecificOptions)) {
            $conversionOptions = array_merge($conversionOptions, $formatSpecificOptions);
        }

        return $conversionOptions;
    }

    /**
     * 构建尺寸调整参数
     */
    protected function buildResizeParams(array $options): array
    {
        $resizeParams = [];
        $width = $options['width'] ?? null;
        $height = $options['height'] ?? null;
        $resizeMode = $options['resize_mode'] ?? 'fit';
        $maintainAspect = $options['maintain_aspect'] ?? true;

        if ($width && $height) {
            if ($maintainAspect) {
                // 保持宽高比
                $resizeParams[] = '-resize';
                $resizeParams[] = "{$width}x{$height}>"; // 只缩小，不放大
            } else {
                // 强制尺寸
                $resizeParams[] = '-resize';
                $resizeParams[] = "{$width}x{$height}!";
            }
        } elseif ($width) {
            $resizeParams[] = '-resize';
            $resizeParams[] = "{$width}x";
        } elseif ($height) {
            $resizeParams[] = '-resize';
            $resizeParams[] = "x{$height}";
        }

        return $resizeParams;
    }

    /**
     * 获取格式特定选项
     */
    protected function getFormatSpecificOptions(string $outputFormat, array $options): array
    {
        $formatOptions = [];

        switch ($outputFormat) {
            case 'png':
                // PNG 特定选项
                if (isset($options['compression'])) {
                    $compression = $options['compression'];
                    if (in_array($compression, ['none', 'fast', 'good', 'best'])) {
                        $formatOptions[] = '-compress';
                        $formatOptions[] = $compression;
                    }
                }
                break;

            case 'jpg':
            case 'jpeg':
                // JPEG 特定选项
                $formatOptions[] = '-interlace';
                $formatOptions[] = 'Plane';
                break;

            case 'webp':
                // WebP 特定选项
                if (isset($options['lossless']) && $options['lossless']) {
                    $formatOptions[] = '-define';
                    $formatOptions[] = 'webp:lossless=true';
                }
                break;

            case 'tiff':
                // TIFF 特定选项
                $formatOptions[] = '-compress';
                $formatOptions[] = 'LZW';
                break;

            case 'heif':
            case 'heic':
                // HEIF/HEIC 特定选项
                if (isset($options['quality'])) {
                    $quality = (int) $options['quality'];
                    if ($quality >= 1 && $quality <= 100) {
                        $formatOptions[] = '-define';
                        $formatOptions[] = "heic:quality={$quality}";
                    }
                }
                // 设置编码器
                $formatOptions[] = '-define';
                $formatOptions[] = 'heic:encoder=x265';
                break;
        }

        return $formatOptions;
    }

    /**
     * 执行ImageMagick命令
     */
    protected function executeImageMagickCommand(array $command): void
    {
        $commandStr = implode(' ', array_map('escapeshellarg', $command));

        Log::info('执行ImageMagick命令', ['command' => $commandStr]);

        $descriptorspec = [
            0 => ['pipe', 'r'],  // stdin
            1 => ['pipe', 'w'],  // stdout
            2 => ['pipe', 'w']   // stderr
        ];

        $process = proc_open($commandStr, $descriptorspec, $pipes);

        if (!is_resource($process)) {
            throw new Exception('无法启动ImageMagick进程');
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
            Log::error('ImageMagick命令执行失败', [
                'command' => $commandStr,
                'return_code' => $returnCode,
                'stdout' => $stdout,
                'stderr' => $stderr
            ]);
            throw new Exception("ImageMagick转换失败: {$stderr}");
        }

        Log::info('ImageMagick命令执行成功', ['command' => $commandStr]);
    }
}
