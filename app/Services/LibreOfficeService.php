<?php

namespace App\Services;

use App\Models\FileConversionTask;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Exception;

class LibreOfficeService extends ConversionServiceBase
{
    /**
     * 支持的输入格式
     */
    const SUPPORTED_INPUT_FORMATS = [
        // Microsoft Office 格式
        'doc',
        'docx',
        'xls',
        'xlsx',
        'ppt',
        'pptx',
        // OpenDocument 格式
        'odt',
        'ods',
        'odp',
        'odg',
        // 其他文档格式
        'rtf',
        'txt',
        'csv',
        'html',
        'htm'
    ];

    /**
     * 支持的输出格式
     */
    const OUTPUT_FORMATS = [
        ['value' => 'pdf', 'label' => 'PDF (Portable Document Format)'],
        ['value' => 'docx', 'label' => 'Word Document (DOCX)'],
        ['value' => 'odt', 'label' => 'OpenDocument Text (ODT)'],
        ['value' => 'xlsx', 'label' => 'Excel Workbook (XLSX)'],
        ['value' => 'ods', 'label' => 'OpenDocument Spreadsheet (ODS)'],
        ['value' => 'pptx', 'label' => 'PowerPoint Presentation (PPTX)'],
        ['value' => 'odp', 'label' => 'OpenDocument Presentation (ODP)'],
        ['value' => 'txt', 'label' => 'Plain Text (TXT)'],
        ['value' => 'rtf', 'label' => 'Rich Text Format (RTF)'],
        ['value' => 'html', 'label' => 'HTML Document'],
        ['value' => 'png', 'label' => 'PNG Image (首页预览)'],
        ['value' => 'jpg', 'label' => 'JPEG Image (首页预览)']
    ];

    /**
     * 文档类型映射
     */
    const DOCUMENT_TYPES = [
        // 文字处理文档
        'writer' => ['doc', 'docx', 'odt', 'rtf', 'txt', 'html', 'htm'],
        // 电子表格
        'calc' => ['xls', 'xlsx', 'ods', 'csv'],
        // 演示文稿
        'impress' => ['ppt', 'pptx', 'odp'],
        // 绘图
        'draw' => ['odg']
    ];

    /**
     * 文件大小限制（50MB）
     */
    const MAX_FILE_SIZE = 50 * 1024 * 1024;

    /**
     * 创建LibreOffice转换任务
     *
     * @param array $data 任务数据
     * @return FileConversionTask
     * @throws Exception
     */
    public function createConversionTask(array $data): FileConversionTask
    {
        try {
            // 验证输出格式
            $outputFormats = array_column(self::OUTPUT_FORMATS, 'value');
            if (!in_array($data['output_format'], $outputFormats)) {
                throw new Exception("不支持的输出格式: {$data['output_format']}");
            }

            // 验证输入格式
            if (!in_array($data['input_format'], self::SUPPORTED_INPUT_FORMATS)) {
                throw new Exception("不支持的输入格式: {$data['input_format']}");
            }

            // 验证格式转换的合理性
            if (!$this->isValidConversion($data['input_format'], $data['output_format'])) {
                throw new Exception("不支持从 {$data['input_format']} 转换到 {$data['output_format']}");
            }

            // 创建任务记录
            $task = FileConversionTask::create([
                'user_id' => $data['user_id'] ?? null,
                'conversion_engine' => 'libreoffice',
                'input_method' => $data['input_method'],
                'input_file' => $data['input_file'],
                'filename' => $data['filename'],
                'input_format' => $data['input_format'],
                'file_size' => $data['file_size'] ?? null,
                'output_format' => $data['output_format'],
                'conversion_options' => $data['conversion_options'] ?? [],
                'status' => FileConversionTask::STATUS_WAIT,
                'step_percent' => 0,
                'callback_url' => $data['callback_url'] ?? null,
                'tag' => $data['tag'] ?? null,
            ]);

            Log::info('LibreOffice转换任务已创建', [
                'task_id' => $task->id,
                'input_format' => $data['input_format'],
                'output_format' => $data['output_format']
            ]);

            return $task;
        } catch (Exception $e) {
            Log::error('创建LibreOffice转换任务失败', [
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * 检查是否支持特定格式转换
     *
     * @param string $inputFormat 输入格式
     * @param string $outputFormat 输出格式
     * @return bool 是否支持该转换
     */
    public function supportsConversion(string $inputFormat, string $outputFormat): bool
    {
        // 检查LibreOffice是否可用
        if (!$this->isLibreOfficeAvailable()) {
            return false;
        }

        // 检查输入格式是否支持
        if (!in_array($inputFormat, self::SUPPORTED_INPUT_FORMATS)) {
            return false;
        }

        // 检查输出格式是否支持
        $outputFormats = array_column(self::OUTPUT_FORMATS, 'value');
        if (!in_array($outputFormat, $outputFormats)) {
            return false;
        }

        // 检查转换的合理性
        return $this->isValidConversion($inputFormat, $outputFormat);
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
            return $this->executeConversion($task);

            return $this->buildSuccessResponse([
                'task_id' => $task->id,
                'status' => 'wait',
                'engine' => 'libreoffice',
                'message' => 'LibreOffice转换任务已提交'
            ], 'LibreOffice转换任务已提交');
        } catch (Exception $e) {
            Log::error('LibreOffice转换任务提交失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getMessage()
            ]);

            return $this->buildErrorResponse('LibreOffice转换任务提交失败: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 执行LibreOffice转换任务
     *
     * @param FileConversionTask $task
     * @return array 转换结果
     */
    public function executeConversion(FileConversionTask $task): array
    {
        try {
            Log::info('开始LibreOffice转换任务', [
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
                throw new Exception("文件过大，请选择小于50MB的文件");
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
                'completed_at' => now()
            ]);

            // 清理临时文件
            $this->cleanupFiles();

            Log::info('LibreOffice转换任务完成', [
                'task_id' => $task->id,
                'output_url' => $outputUrl,
                'output_size' => $outputSize
            ]);

            return $this->buildSuccessResponse([
                'task_id' => $task->id,
                'status' => 'finished',
                'output_url' => $outputUrl,
                'output_size' => $outputSize
            ], 'LibreOffice转换任务完成');
        } catch (Exception $e) {
            Log::error('LibreOffice转换任务失败', [
                'task_id' => $task->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // 更新任务状态为失败
            $task->update([
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

            return $this->buildErrorResponse('LibreOffice转换任务失败: ' . $e->getMessage(), 500);
        }
    }

    /**
     * 创建并执行转换任务
     *
     * @param array $data 任务数据
     * @return array 转换结果
     * @throws Exception
     */
    public function createAndExecuteTask(array $data): array
    {
        $task = $this->createConversionTask($data);
        return $this->executeConversion($task);
    }

    /**
     * 重写服务名称
     *
     * @return string
     */
    public function getServiceName(): string
    {
        return 'libreoffice';
    }

    /**
     * 重写服务可用性检查
     *
     * @return bool
     */
    public function isAvailable(): bool
    {
        return $this->isLibreOfficeAvailable();
    }

    /**
     * 验证转换选项
     *
     * @param array $options
     * @return bool
     */
    public function validateConversionOptions(array $options): bool
    {
        // LibreOffice 转换选项相对简单，主要验证格式兼容性
        return true;
    }

    /**
     * 检查LibreOffice是否可用
     *
     * @return bool
     */
    public function isLibreOfficeAvailable(): bool
    {
        try {
            $output = [];
            $returnCode = 0;
            exec('libreoffice --version 2>&1', $output, $returnCode);
            return $returnCode === 0;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 获取LibreOffice版本信息
     *
     * @return string|null
     */
    public function getLibreOfficeVersion(): ?string
    {
        try {
            $output = [];
            exec('libreoffice --version 2>&1', $output);
            if (!empty($output)) {
                // 解析版本信息
                foreach ($output as $line) {
                    if (preg_match('/LibreOffice\s+([^\s]+)/', $line, $matches)) {
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
     * 判断是否应该使用LibreOffice进行转换
     *
     * @param string $inputFormat
     * @param string $outputFormat
     * @return bool
     */
    public function shouldUseLibreOffice(string $inputFormat, string $outputFormat): bool
    {
        // 检查LibreOffice是否可用
        if (!$this->isLibreOfficeAvailable()) {
            Log::info('LibreOffice不可用', [
                'input_format' => $inputFormat,
                'output_format' => $outputFormat
            ]);
            return false;
        }

        // 检查输入格式是否支持
        if (!in_array($inputFormat, self::SUPPORTED_INPUT_FORMATS)) {
            return false;
        }

        // 检查输出格式是否支持
        $outputFormats = array_column(self::OUTPUT_FORMATS, 'value');
        if (!in_array($outputFormat, $outputFormats)) {
            return false;
        }

        // 检查转换的合理性
        if (!$this->isValidConversion($inputFormat, $outputFormat)) {
            return false;
        }

        Log::info('使用LibreOffice进行转换', [
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
        // 获取输入格式的文档类型
        $inputDocType = $this->getDocumentType($inputFormat);

        // 特殊情况：PDF和图片输出支持所有输入格式
        if (in_array($outputFormat, ['pdf', 'png', 'jpg'])) {
            return true;
        }

        // 文本格式输出支持所有输入格式
        if (in_array($outputFormat, ['txt', 'rtf', 'html'])) {
            return true;
        }

        // 相同文档类型之间的转换
        $outputDocType = $this->getDocumentType($outputFormat);

        // 允许同类型文档之间转换
        if ($inputDocType && $outputDocType && $inputDocType === $outputDocType) {
            return true;
        }

        // 特殊规则：Writer文档可以转换为演示文稿格式（内容导入）
        if ($inputDocType === 'writer' && $outputDocType === 'impress') {
            return true;
        }

        return false;
    }

    /**
     * 获取文档类型
     *
     * @param string $format
     * @return string|null
     */
    protected function getDocumentType(string $format): ?string
    {
        foreach (self::DOCUMENT_TYPES as $type => $formats) {
            if (in_array($format, $formats)) {
                return $type;
            }
        }
        return null;
    }

    /**
     * 获取LibreOffice转换命令
     *
     * @param string $inputFile
     * @param string $outputDir
     * @param string $outputFormat
     * @param array $options
     * @return array
     */
    public function getConversionCommand(string $inputFile, string $outputDir, string $outputFormat, array $options = []): array
    {
        $command = [
            'libreoffice',
            '--headless',
            '--convert-to',
            $outputFormat,
            '--outdir',
            $outputDir,
            $inputFile
        ];

        // 添加特定格式的选项
        if ($outputFormat === 'pdf') {
            // PDF 特定选项
            $command = array_merge($command, ['--invisible']);
        }

        return $command;
    }

    /**
     * 验证格式支持
     */
    protected function validateFormats(FileConversionTask $task): void
    {
        if (!in_array($task->input_format, self::SUPPORTED_INPUT_FORMATS)) {
            throw new Exception("不支持的输入格式: {$task->input_format}");
        }

        if (!in_array($task->output_format, array_column(self::OUTPUT_FORMATS, 'value'))) {
            throw new Exception("不支持的输出格式: {$task->output_format}");
        }
    }

    /**
     * 执行LibreOffice转换
     */
    protected function performConversion(FileConversionTask $task, string $inputFilePath): string
    {
        $task->updateProgress(20);

        $tempDir = dirname($inputFilePath);
        $outputFormat = $task->output_format;
        $options = $task->getConversionOptions();

        // 特殊处理图片格式输出
        if (in_array($outputFormat, ['png', 'jpg'])) {
            return $this->convertToImage($task, $inputFilePath, $tempDir, $outputFormat);
        }

        // 标准文档转换
        return $this->convertDocument($task, $inputFilePath, $tempDir, $outputFormat, $options);
    }

    /**
     * 标准文档转换
     */
    protected function convertDocument(FileConversionTask $task, string $inputFilePath, string $tempDir, string $outputFormat, array $options): string
    {
        $task->updateProgress(30);

        // 构建LibreOffice转换命令
        $command = [
            'libreoffice',
            '--headless',
            '--invisible',
            '--nodefault',
            '--nolockcheck',
            '--nologo',
            '--norestore',
            '--convert-to',
            $outputFormat,
            '--outdir',
            $tempDir,
            $inputFilePath
        ];

        $task->updateProgress(50);

        // 执行转换命令
        $this->executeLibreOfficeCommand($command);

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
     * 转换为图片格式（首页预览）
     */
    protected function convertToImage(FileConversionTask $task, string $inputFilePath, string $tempDir, string $imageFormat): string
    {
        $task->updateProgress(30);

        // 首先转换为PDF
        $pdfCommand = [
            'libreoffice',
            '--headless',
            '--invisible',
            '--nodefault',
            '--nolockcheck',
            '--nologo',
            '--norestore',
            '--convert-to',
            'pdf',
            '--outdir',
            $tempDir,
            $inputFilePath
        ];

        $this->executeLibreOfficeCommand($pdfCommand);

        $task->updateProgress(60);

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
                '-quality',
                '90',
                '-density',
                '150',
                $imageFilePath
            ];

            $this->executeCommand($convertCommand);
        } else {
            // 如果没有ImageMagick，返回PDF文件
            Log::info('ImageMagick不可用，返回PDF文件而不是图片', [
                'task_id' => $task->id,
                'requested_format' => $imageFormat
            ]);
            return $pdfFilePath;
        }

        $task->updateProgress(90);

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
}
