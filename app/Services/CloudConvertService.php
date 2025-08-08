<?php

namespace App\Services;

use CloudConvert\Laravel\Facades\CloudConvert;
use CloudConvert\Models\Job;
use CloudConvert\Models\Task;
use Illuminate\Support\Facades\Log;
use Exception;

class CloudConvertService
{
    /**
     * API 配置
     */
    private array $config;

    /**
     * 超时时间（秒）
     */
    private int $timeout;

    /**
     * 最大重试次数
     */
    private int $maxRetries;

    /**
     * 构造函数
     */
    public function __construct()
    {
        $this->config = config('services.cloudconvert', []);
        $this->timeout = $this->config['timeout'] ?? 30;
        $this->maxRetries = $this->config['max_retries'] ?? 3;

        if (empty($this->config['api_key'])) {
            throw new Exception('CloudConvert API密钥未配置');
        }
    }

    /**
     * 开始转换任务
     *
     * @param array $params 转换参数
     * @return array
     */
    public function startConversion(array $params): array
    {
        try {
            $inputUrl = $params['input_url'] ?? null;
            $outputFormat = $params['output_format'] ?? 'mp4';
            $options = $params['options'] ?? [];
            $tag = $params['tag'] ?? 'conversion-' . uniqid();

            if (empty($inputUrl)) {
                return [
                    'success' => false,
                    'error' => '输入URL不能为空',
                    'code' => 400
                ];
            }

            // 创建转换任务
            $job = (new Job())
                ->setTag($tag)
                ->addTask(
                    (new Task('import/url', 'import-file'))
                        ->set('url', $inputUrl)
                )
                ->addTask(
                    (new Task('convert', 'convert-file'))
                        ->set('input', 'import-file')
                        ->set('output_format', $outputFormat)
                        ->set('options', $options)
                )
                ->addTask(
                    (new Task('export/url', 'export-file'))
                        ->set('input', 'convert-file')
                );

            $createdJob = CloudConvert::jobs()->create($job);

            Log::info('CloudConvert转换任务已创建', [
                'job_id' => $createdJob->getId(),
                'tag' => $tag,
                'input_url' => $inputUrl,
                'output_format' => $outputFormat
            ]);

            return [
                'success' => true,
                'data' => [
                    'job_id' => $createdJob->getId(),
                    'tag' => $tag,
                    'status' => 'created',
                    'tasks' => $createdJob->getTasks()->toArray()
                ],
                'code' => 200
            ];

        } catch (Exception $e) {
            Log::error('CloudConvert转换任务创建失败', [
                'error' => $e->getMessage(),
                'params' => $params
            ]);

            return [
                'success' => false,
                'error' => '转换任务创建失败: ' . $e->getMessage(),
                'code' => 500
            ];
        }
    }

    /**
     * 获取转换状态
     *
     * @param string $jobId 任务ID
     * @return array
     */
    public function getStatus(string $jobId): array
    {
        try {
            $job = CloudConvert::jobs()->get($jobId);
            $tasks = $job->getTasks();

            // 获取各个任务状态
            $importTask = $tasks->whereName('import-file')->first();
            $convertTask = $tasks->whereName('convert-file')->first();
            $exportTask = $tasks->whereName('export-file')->first();

            $status = 'processing';
            $progress = 0;
            $error = null;

            // 检查任务状态
            if ($importTask && $importTask->getStatus() === 'error') {
                $status = 'error';
                $error = $importTask->getMessage();
            } elseif ($convertTask && $convertTask->getStatus() === 'error') {
                $status = 'error';
                $error = $convertTask->getMessage();
            } elseif ($exportTask && $exportTask->getStatus() === 'error') {
                $status = 'error';
                $error = $exportTask->getMessage();
            } elseif ($exportTask && $exportTask->getStatus() === 'finished') {
                $status = 'finished';
                $progress = 100;
            } elseif ($convertTask && $convertTask->getStatus() === 'finished') {
                $progress = 66;
            } elseif ($importTask && $importTask->getStatus() === 'finished') {
                $progress = 33;
            }

            $result = [
                'success' => true,
                'data' => [
                    'job_id' => $jobId,
                    'status' => $status,
                    'progress' => $progress,
                    'tag' => $job->getTag(),
                    'created_at' => $job->getCreatedAt(),
                    'started_at' => $job->getStartedAt(),
                    'finished_at' => $job->getFinishedAt(),
                    'tasks' => [
                        'import' => $importTask ? [
                            'status' => $importTask->getStatus(),
                            'message' => $importTask->getMessage()
                        ] : null,
                        'convert' => $convertTask ? [
                            'status' => $convertTask->getStatus(),
                            'message' => $convertTask->getMessage()
                        ] : null,
                        'export' => $exportTask ? [
                            'status' => $exportTask->getStatus(),
                            'message' => $exportTask->getMessage(),
                            'result' => $exportTask->getResult()
                        ] : null
                    ]
                ],
                'code' => 200
            ];

            if ($error) {
                $result['data']['error'] = $error;
            }

            return $result;

        } catch (Exception $e) {
            Log::error('CloudConvert状态查询失败', [
                'job_id' => $jobId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => '状态查询失败: ' . $e->getMessage(),
                'code' => 500
            ];
        }
    }

    /**
     * 等待任务完成
     *
     * @param string $jobId 任务ID
     * @param int $timeout 超时时间（秒）
     * @return array
     */
    public function waitForJob(string $jobId, int $timeout = 300): array
    {
        try {
            $job = CloudConvert::jobs()->wait($jobId, $timeout);
            return $this->getStatus($jobId);

        } catch (Exception $e) {
            Log::error('CloudConvert任务等待失败', [
                'job_id' => $jobId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => '任务等待失败: ' . $e->getMessage(),
                'code' => 500
            ];
        }
    }

    /**
     * 取消转换任务
     *
     * @param string $jobId 任务ID
     * @return array
     */
    public function cancelConversion(string $jobId): array
    {
        try {
            CloudConvert::jobs()->cancel($jobId);

            Log::info('CloudConvert任务已取消', [
                'job_id' => $jobId
            ]);

            return [
                'success' => true,
                'data' => [
                    'job_id' => $jobId,
                    'status' => 'cancelled'
                ],
                'code' => 200
            ];

        } catch (Exception $e) {
            Log::error('CloudConvert任务取消失败', [
                'job_id' => $jobId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => '任务取消失败: ' . $e->getMessage(),
                'code' => 500
            ];
        }
    }

    /**
     * 获取支持的格式
     *
     * @return array
     */
    public function getSupportedFormats(): array
    {
        return [
            'video' => [
                'input' => ['mp4', 'avi', 'mov', 'wmv', 'flv', 'webm', 'mkv', 'm4v', '3gp', 'ogv'],
                'output' => ['mp4', 'avi', 'mov', 'wmv', 'flv', 'webm', 'mkv', 'm4v', '3gp', 'ogv', 'gif']
            ],
            'audio' => [
                'input' => ['mp3', 'wav', 'aac', 'ogg', 'wma', 'flac', 'm4a', 'opus'],
                'output' => ['mp3', 'wav', 'aac', 'ogg', 'wma', 'flac', 'm4a', 'opus']
            ],
            'image' => [
                'input' => ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'tiff', 'webp', 'svg'],
                'output' => ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'tiff', 'webp', 'svg', 'ico']
            ],
            'document' => [
                'input' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'rtf'],
                'output' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'rtf', 'html']
            ]
        ];
    }

    /**
     * 验证格式是否支持
     *
     * @param string $inputFormat 输入格式
     * @param string $outputFormat 输出格式
     * @return bool
     */
    public function validateFormat(string $inputFormat, string $outputFormat): bool
    {
        $formats = $this->getSupportedFormats();
        
        foreach ($formats as $category) {
            if (in_array(strtolower($inputFormat), $category['input']) && 
                in_array(strtolower($outputFormat), $category['output'])) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * 下载转换结果
     *
     * @param string $jobId 任务ID
     * @param string $outputPath 输出路径
     * @return array
     */
    public function downloadResult(string $jobId, string $outputPath): array
    {
        try {
            $job = CloudConvert::jobs()->get($jobId);
            $exportTask = $job->getTasks()->whereName('export-file')->first();

            if (!$exportTask || $exportTask->getStatus() !== 'finished') {
                return [
                    'success' => false,
                    'error' => '任务未完成或导出失败',
                    'code' => 400
                ];
            }

            $result = $exportTask->getResult();
            $files = $result['files'] ?? [];

            if (empty($files)) {
                return [
                    'success' => false,
                    'error' => '没有可下载的文件',
                    'code' => 404
                ];
            }

            $downloadedFiles = [];

            foreach ($files as $file) {
                $url = $file['url'];
                $filename = $file['filename'] ?? basename($url);
                $filePath = rtrim($outputPath, '/') . '/' . $filename;

                // 确保目录存在
                $dir = dirname($filePath);
                if (!is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }

                // 下载文件
                $source = CloudConvert::getHttpTransport()->download($url)->detach();
                $dest = fopen($filePath, 'w');
                
                if ($source && $dest) {
                    stream_copy_to_stream($source, $dest);
                    fclose($dest);
                    fclose($source);
                    
                    $downloadedFiles[] = [
                        'filename' => $filename,
                        'path' => $filePath,
                        'size' => filesize($filePath)
                    ];
                }
            }

            Log::info('CloudConvert文件下载完成', [
                'job_id' => $jobId,
                'files' => $downloadedFiles
            ]);

            return [
                'success' => true,
                'data' => [
                    'job_id' => $jobId,
                    'files' => $downloadedFiles,
                    'count' => count($downloadedFiles)
                ],
                'code' => 200
            ];

        } catch (Exception $e) {
            Log::error('CloudConvert文件下载失败', [
                'job_id' => $jobId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => '文件下载失败: ' . $e->getMessage(),
                'code' => 500
            ];
        }
    }

    /**
     * 创建上传任务
     *
     * @param string $filename 文件名
     * @param string $outputFormat 输出格式
     * @param array $options 转换选项
     * @return array
     */
    public function createUploadJob(string $filename, string $outputFormat, array $options = []): array
    {
        try {
            $tag = 'upload-' . uniqid();

            $job = (new Job())
                ->setTag($tag)
                ->addTask(
                    (new Task('import/upload', 'upload-file'))
                        ->set('filename', $filename)
                )
                ->addTask(
                    (new Task('convert', 'convert-file'))
                        ->set('input', 'upload-file')
                        ->set('output_format', $outputFormat)
                        ->set('options', $options)
                )
                ->addTask(
                    (new Task('export/url', 'export-file'))
                        ->set('input', 'convert-file')
                );

            $createdJob = CloudConvert::jobs()->create($job);
            $uploadTask = $createdJob->getTasks()->whereName('upload-file')->first();

            Log::info('CloudConvert上传任务已创建', [
                'job_id' => $createdJob->getId(),
                'filename' => $filename,
                'output_format' => $outputFormat
            ]);

            return [
                'success' => true,
                'data' => [
                    'job_id' => $createdJob->getId(),
                    'upload_task' => $uploadTask,
                    'tag' => $tag
                ],
                'code' => 200
            ];

        } catch (Exception $e) {
            Log::error('CloudConvert上传任务创建失败', [
                'error' => $e->getMessage(),
                'filename' => $filename
            ]);

            return [
                'success' => false,
                'error' => '上传任务创建失败: ' . $e->getMessage(),
                'code' => 500
            ];
        }
    }

    /**
     * 上传文件
     *
     * @param Task $uploadTask 上传任务
     * @param string $filePath 文件路径
     * @return array
     */
    public function uploadFile(Task $uploadTask, string $filePath): array
    {
        try {
            if (!file_exists($filePath)) {
                return [
                    'success' => false,
                    'error' => '文件不存在: ' . $filePath,
                    'code' => 404
                ];
            }

            $inputStream = fopen($filePath, 'r');
            CloudConvert::tasks()->upload($uploadTask, $inputStream);

            Log::info('CloudConvert文件上传完成', [
                'task_id' => $uploadTask->getId(),
                'file_path' => $filePath
            ]);

            return [
                'success' => true,
                'data' => [
                    'task_id' => $uploadTask->getId(),
                    'file_path' => $filePath
                ],
                'code' => 200
            ];

        } catch (Exception $e) {
            Log::error('CloudConvert文件上传失败', [
                'error' => $e->getMessage(),
                'file_path' => $filePath
            ]);

            return [
                'success' => false,
                'error' => '文件上传失败: ' . $e->getMessage(),
                'code' => 500
            ];
        }
    }

    /**
     * 估算转换时间
     *
     * @param string $inputFormat 输入格式
     * @param string $outputFormat 输出格式
     * @param int $fileSize 文件大小（字节）
     * @return int 估算时间（秒）
     */
    public function estimateConversionTime(string $inputFormat, string $outputFormat, int $fileSize): int
    {
        // 基础转换时间（秒）
        $baseTime = 30;
        
        // 根据文件大小调整
        $sizeFactor = $fileSize / (10 * 1024 * 1024); // 10MB 基准
        
        // 根据格式复杂度调整
        $formatComplexity = 1.0;
        if (in_array($inputFormat, ['avi', 'mov', 'mkv'])) {
            $formatComplexity = 1.5;
        }
        if (in_array($outputFormat, ['gif', 'webm'])) {
            $formatComplexity = 1.3;
        }
        
        return (int) ($baseTime * $sizeFactor * $formatComplexity);
    }
}
