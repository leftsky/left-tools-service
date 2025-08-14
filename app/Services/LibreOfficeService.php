<?php

namespace App\Services;

use App\Jobs\LibreOfficeConversionJob;
use App\Models\FileConversionTask;
use Illuminate\Support\Facades\Log;
use Exception;

class LibreOfficeService
{
    /**
     * 支持的输入格式
     */
    const SUPPORTED_INPUT_FORMATS = [
        // Microsoft Office 格式
        'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
        // OpenDocument 格式
        'odt', 'ods', 'odp', 'odg',
        // 其他文档格式
        'rtf', 'txt', 'csv', 'html', 'htm'
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
     * 调度LibreOffice转换任务
     *
     * @param FileConversionTask $task
     * @return void
     */
    public function dispatchConversionJob(FileConversionTask $task): void
    {
        try {
            // 调度异步任务
            LibreOfficeConversionJob::dispatch($task)->onQueue('local-conversion');

            Log::info('LibreOffice转换任务已调度', [
                'task_id' => $task->id
            ]);

        } catch (Exception $e) {
            Log::error('调度LibreOffice转换任务失败', [
                'task_id' => $task->id,
                'error' => $e->getMessage()
            ]);

            // 更新任务状态为失败
            $task->update([
                'status' => FileConversionTask::STATUS_FAILED,
                'error_message' => '任务调度失败: ' . $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * 创建并调度转换任务
     *
     * @param array $data 任务数据
     * @return FileConversionTask
     * @throws Exception
     */
    public function createAndDispatchTask(array $data): FileConversionTask
    {
        $task = $this->createConversionTask($data);
        $this->dispatchConversionJob($task);
        return $task;
    }

    /**
     * 获取支持的配置选项
     *
     * @return array
     */
    public function getSupportedConfigs(): array
    {
        return [
            'inputFormats' => self::SUPPORTED_INPUT_FORMATS,
            'outputFormats' => self::OUTPUT_FORMATS,
            'documentTypes' => self::DOCUMENT_TYPES,
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
            '--convert-to', $outputFormat,
            '--outdir', $outputDir,
            $inputFile
        ];

        // 添加特定格式的选项
        if ($outputFormat === 'pdf') {
            // PDF 特定选项
            $command = array_merge($command, ['--invisible']);
        }

        return $command;
    }
}
