<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Exception;

class ConvertioService
{
    /**
     * Convertio API基础URL
     */
    private const API_BASE_URL = 'https://api.convertio.co';

    /**
     * API密钥
     */
    private string $apiKey;

    /**
     * HTTP客户端超时时间（秒）
     */
    private int $timeout = 30;

    /**
     * 最大重试次数
     */
    private int $maxRetries = 3;

    /**
     * 构造函数
     */
    public function __construct()
    {
        $this->apiKey = config('services.convertio.api_key', env('CONVERTIO_API_KEY'));

        if (empty($this->apiKey)) {
            throw new Exception('Convertio API密钥未配置');
        }
    }

    /**
     * 开始转换
     *
     * @param array $params 转换参数
     * @return array
     */
    public function startConversion(array $params): array
    {
        try {
            $requestData = array_merge([
                'apikey' => $this->apiKey,
                'input' => 'url', // 默认使用URL输入
            ], $params);

            $response = $this->makeRequest('POST', '/convert', $requestData);

            if ($response['success']) {
                Log::info('Convertio转换任务已创建', [
                    'conversion_id' => $response['data']['id'] ?? null,
                    'params' => $params
                ]);
            }

            return $response;
        } catch (Exception $e) {
            Log::error('Convertio转换任务创建失败', [
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
     * @param string $conversionId 转换ID
     * @return array
     */
    public function getStatus(string $conversionId): array
    {
        try {
            $response = $this->makeRequest('GET', "/convert/{$conversionId}/status");

            if ($response['success']) {
                Log::info('Convertio转换状态查询成功', [
                    'conversion_id' => $conversionId,
                    'status' => $response['data']['step'] ?? null,
                    'progress' => $response['data']['step_percent'] ?? null
                ]);
            }

            return $response;
        } catch (Exception $e) {
            Log::error('Convertio转换状态查询失败', [
                'conversion_id' => $conversionId,
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
     * 下载结果文件
     *
     * @param string $conversionId 转换ID
     * @param string|null $type 文件类型（base64等）
     * @return array
     */
    public function downloadResult(string $conversionId, ?string $type = null): array
    {
        try {
            $url = "/convert/{$conversionId}/dl";
            if ($type) {
                $url .= "/{$type}";
            }

            $response = $this->makeRequest('GET', $url);

            if ($response['success']) {
                Log::info('Convertio结果文件下载成功', [
                    'conversion_id' => $conversionId,
                    'type' => $type
                ]);
            }

            return $response;
        } catch (Exception $e) {
            Log::error('Convertio结果文件下载失败', [
                'conversion_id' => $conversionId,
                'type' => $type,
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
     * 取消转换
     *
     * @param string $conversionId 转换ID
     * @return array
     */
    public function cancelConversion(string $conversionId): array
    {
        try {
            $response = $this->makeRequest('DELETE', "/convert/{$conversionId}");

            if ($response['success']) {
                Log::info('Convertio转换任务已取消', [
                    'conversion_id' => $conversionId
                ]);
            }

            return $response;
        } catch (Exception $e) {
            Log::error('Convertio转换任务取消失败', [
                'conversion_id' => $conversionId,
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
     * 获取转换列表
     *
     * @param array $params 查询参数
     * @return array
     */
    public function getConversionList(array $params = []): array
    {
        try {
            $requestData = array_merge([
                'apikey' => $this->apiKey,
                'status' => 'all',
                'count' => 10
            ], $params);

            $response = $this->makeRequest('POST', '/convert/list', $requestData);

            if ($response['success']) {
                Log::info('Convertio转换列表查询成功', [
                    'count' => count($response['data'] ?? [])
                ]);
            }

            return $response;
        } catch (Exception $e) {
            Log::error('Convertio转换列表查询失败', [
                'error' => $e->getMessage(),
                'params' => $params
            ]);

            return [
                'success' => false,
                'error' => '列表查询失败: ' . $e->getMessage(),
                'code' => 500
            ];
        }
    }

    /**
     * 带OCR的转换
     *
     * @param array $params 转换参数
     * @param array $ocrSettings OCR设置
     * @return array
     */
    public function startOcrConversion(array $params, array $ocrSettings = []): array
    {
        $defaultOcrSettings = [
            'ocr_enabled' => true,
            'ocr_settings' => [
                'langs' => ['eng', 'chi_sim'], // 默认支持英文和简体中文
                'page_nums' => null // 所有页面
            ]
        ];

        $options = array_merge($defaultOcrSettings, $ocrSettings);
        $params['options'] = $options;

        return $this->startConversion($params);
    }

    /**
     * 带回调的转换
     *
     * @param array $params 转换参数
     * @param string $callbackUrl 回调URL
     * @return array
     */
    public function startConversionWithCallback(array $params, string $callbackUrl): array
    {
        $options = [
            'callback_url' => $callbackUrl
        ];

        if (isset($params['options'])) {
            $options = array_merge($options, $params['options']);
        }

        $params['options'] = $options;

        return $this->startConversion($params);
    }

    /**
     * 文件上传转换
     *
     * @param string $conversionId 转换ID
     * @param string $filePath 文件路径
     * @param string $filename 文件名
     * @return array
     */
    public function uploadFileAndConvert(string $conversionId, string $filePath, string $filename): array
    {
        try {
            if (!file_exists($filePath)) {
                throw new Exception('文件不存在: ' . $filePath);
            }

            $response = Http::timeout($this->timeout)
                ->put(self::API_BASE_URL . "/convert/{$conversionId}/{$filename}", [
                    'file' => fopen($filePath, 'r')
                ]);

            $responseData = $response->json();

            if ($response->successful() && isset($responseData['status']) && $responseData['status'] === 'ok') {
                Log::info('Convertio文件上传成功', [
                    'conversion_id' => $conversionId,
                    'filename' => $filename,
                    'file_size' => $responseData['data']['size'] ?? null
                ]);

                return [
                    'success' => true,
                    'data' => $responseData['data'],
                    'code' => $response->status()
                ];
            } else {
                $error = $responseData['error'] ?? '文件上传失败';
                Log::error('Convertio文件上传失败', [
                    'conversion_id' => $conversionId,
                    'filename' => $filename,
                    'error' => $error
                ]);

                return [
                    'success' => false,
                    'error' => $error,
                    'code' => $response->status()
                ];
            }
        } catch (Exception $e) {
            Log::error('Convertio文件上传异常', [
                'conversion_id' => $conversionId,
                'filename' => $filename,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => '文件上传异常: ' . $e->getMessage(),
                'code' => 500
            ];
        }
    }

    /**
     * 创建上传转换任务
     *
     * @param string $filename 文件名
     * @param string $outputFormat 输出格式
     * @param array $options 其他选项
     * @return array
     */
    public function createUploadConversion(string $filename, string $outputFormat, array $options = []): array
    {
        $params = [
            'input' => 'upload',
            'filename' => $filename,
            'outputformat' => $outputFormat
        ];

        if (!empty($options)) {
            $params['options'] = $options;
        }

        return $this->startConversion($params);
    }

    /**
     * 从Base64内容开始转换
     *
     * @param string $base64Content Base64编码的文件内容
     * @param string $filename 文件名
     * @param string $outputFormat 输出格式
     * @param array $options 其他选项
     * @return array
     */
    public function startBase64Conversion(string $base64Content, string $filename, string $outputFormat, array $options = []): array
    {
        $params = [
            'input' => 'base64',
            'file' => $base64Content,
            'filename' => $filename,
            'outputformat' => $outputFormat
        ];

        if (!empty($options)) {
            $params['options'] = $options;
        }

        return $this->startConversion($params);
    }

    /**
     * 从原始内容开始转换
     *
     * @param string $rawContent 原始文件内容
     * @param string $filename 文件名
     * @param string $outputFormat 输出格式
     * @param array $options 其他选项
     * @return array
     */
    public function startRawConversion(string $rawContent, string $filename, string $outputFormat, array $options = []): array
    {
        $params = [
            'input' => 'raw',
            'file' => $rawContent,
            'filename' => $filename,
            'outputformat' => $outputFormat
        ];

        if (!empty($options)) {
            $params['options'] = $options;
        }

        return $this->startConversion($params);
    }

    /**
     * 从URL开始转换
     *
     * @param string $fileUrl 文件URL
     * @param string $outputFormat 输出格式
     * @param array $options 其他选项
     * @return array
     */
    public function startUrlConversion(string $fileUrl, string $outputFormat, array $options = []): array
    {
        $params = [
            'input' => 'url',
            'file' => $fileUrl,
            'outputformat' => $outputFormat
        ];

        if (!empty($options)) {
            $params['options'] = $options;
        }

        return $this->startConversion($params);
    }

    /**
     * 发送HTTP请求
     *
     * @param string $method HTTP方法
     * @param string $endpoint API端点
     * @param array $data 请求数据
     * @return array
     */
    private function makeRequest(string $method, string $endpoint, array $data = []): array
    {
        $attempts = 0;
        $lastException = null;

        while ($attempts < $this->maxRetries) {
            try {
                $url = self::API_BASE_URL . $endpoint;

                $response = match ($method) {
                    'GET' => Http::timeout($this->timeout)->get($url),
                    'POST' => Http::timeout($this->timeout)->post($url, $data),
                    'DELETE' => Http::timeout($this->timeout)->delete($url),
                    default => throw new Exception("不支持的HTTP方法: {$method}")
                };

                $responseData = $response->json();

                if ($response->successful() && isset($responseData['status'])) {
                    if ($responseData['status'] === 'ok') {
                        return [
                            'success' => true,
                            'data' => $responseData['data'] ?? $responseData,
                            'code' => $response->status()
                        ];
                    } else {
                        return [
                            'success' => false,
                            'error' => $responseData['error'] ?? 'API返回错误',
                            'code' => $response->status()
                        ];
                    }
                } else {
                    return [
                        'success' => false,
                        'error' => 'API响应格式错误',
                        'code' => $response->status(),
                        'response' => $responseData
                    ];
                }
            } catch (Exception $e) {
                $lastException = $e;
                $attempts++;

                if ($attempts < $this->maxRetries) {
                    Log::warning("Convertio API请求失败，准备重试", [
                        'attempt' => $attempts,
                        'method' => $method,
                        'endpoint' => $endpoint,
                        'error' => $e->getMessage()
                    ]);

                    // 指数退避重试
                    sleep(pow(2, $attempts));
                }
            }
        }

        Log::error("Convertio API请求最终失败", [
            'method' => $method,
            'endpoint' => $endpoint,
            'attempts' => $attempts,
            'error' => $lastException->getMessage()
        ]);

        return [
            'success' => false,
            'error' => 'API请求失败: ' . $lastException->getMessage(),
            'code' => 500
        ];
    }

    /**
     * 获取支持的语言代码列表
     *
     * @return array
     */
    public static function getSupportedLanguages(): array
    {
        return [
            'afr' => 'Afrikaans',
            'sqi' => 'Albanian',
            'ara' => 'Arabic (Saudi Arabia)',
            'arm_east' => 'Armenian (Eastern)',
            'arm_west' => 'Armenian (Western)',
            'aze_cyrl' => 'Azeri (Cyrillic)',
            'aze' => 'Azeri (Latin)',
            'eus' => 'Basque',
            'bel' => 'Belarusian',
            'bul' => 'Bulgarian',
            'cat' => 'Catalan',
            'ceb' => 'Cebuano',
            'chi_sim' => 'Chinese Simplified',
            'chi_tra' => 'Chinese Traditional',
            'hrv' => 'Croatian',
            'ces' => 'Czech',
            'dan' => 'Danish',
            'dut' => 'Dutch',
            'nld' => 'Dutch (Belgian)',
            'eng' => 'English',
            'epo' => 'Esperanto',
            'est' => 'Estonian',
            'fij' => 'Fijian',
            'fin' => 'Finnish',
            'fra' => 'French',
            'glg' => 'Galician',
            'deu' => 'German',
            'grk' => 'Greek',
            'haw' => 'Hawaiian',
            'heb' => 'Hebrew',
            'hun' => 'Hungarian',
            'isl' => 'Icelandic',
            'ind' => 'Indonesian',
            'gle' => 'Irish',
            'ita' => 'Italian',
            'jpn' => 'Japanese',
            'kaz' => 'Kazakh',
            'kir' => 'Kirghiz',
            'kon' => 'Kongo',
            'kor' => 'Korean',
            'kur' => 'Kurdish',
            'lat' => 'Latin',
            'lav' => 'Latvian',
            'lit' => 'Lithuanian',
            'mkd' => 'Macedonian',
            'mal' => 'Malay (Malaysian)',
            'mlt' => 'Maltese',
            'nor' => 'Norwegian (Bokmal)',
            'pol' => 'Polish',
            'por' => 'Portuguese',
            'bra' => 'Portuguese (Brazilian)',
            'ron' => 'Romanian',
            'rus' => 'Russian',
            'sco' => 'Scottish',
            'srp' => 'Serbian (Cyrillic)',
            'srp_latn' => 'Serbian (Latin)',
            'slk' => 'Slovak',
            'slv' => 'Slovenian',
            'som' => 'Somali',
            'spa' => 'Spanish',
            'swa' => 'Swahili',
            'swe' => 'Swedish',
            'tgl' => 'Tagalog',
            'tah' => 'Tahitian',
            'tgk' => 'Tajik',
            'tat' => 'Tatar',
            'tha' => 'Thai',
            'tur' => 'Turkish',
            'turk' => 'Turkmen',
            'uig_cyr' => 'Uighur (Cyrillic)',
            'uig' => 'Uighur (Latin)',
            'ukr' => 'Ukrainian',
            'uzb_cyrl' => 'Uzbek (Cyrillic)',
            'uzb' => 'Uzbek (Latin)',
            'vie' => 'Vietnamese',
            'cym' => 'Welsh'
        ];
    }

    /**
     * 获取支持OCR的输出格式
     *
     * @return array
     */
    public static function getOcrSupportedFormats(): array
    {
        return [
            'TXT',
            'RTF',
            'DOCX',
            'XLSX',
            'XLS',
            'CSV',
            'PPTX',
            'PDF',
            'EPUB',
            'DJVU',
            'FB2'
        ];
    }
}

/*
使用示例:

// 1. 从URL转换文件
$convertio = new ConvertioService();
$result = $convertio->startUrlConversion(
    'https://example.com/document.pdf',
    'docx'
);

// 2. 带OCR的转换
$result = $convertio->startOcrConversion([
    'input' => 'url',
    'file' => 'https://example.com/image.jpg',
    'outputformat' => 'txt'
], [
    'ocr_settings' => [
        'langs' => ['eng', 'chi_sim'],
        'page_nums' => '1-3'
    ]
]);

// 3. 检查转换状态
$status = $convertio->getStatus($conversionId);

// 4. 下载结果
$download = $convertio->downloadResult($conversionId);

// 5. 从Base64内容转换
$result = $convertio->startBase64Conversion(
    base64_encode(file_get_contents('document.pdf')),
    'document.pdf',
    'docx'
);

// 6. 文件上传转换
$result = $convertio->createUploadConversion('document.pdf', 'docx');
$upload = $convertio->uploadFileAndConvert($result['data']['id'], '/path/to/file.pdf', 'document.pdf');

// 7. 带回调的转换
$result = $convertio->startConversionWithCallback([
    'input' => 'url',
    'file' => 'https://example.com/document.pdf',
    'outputformat' => 'docx'
], 'https://your-domain.com/api/convertio/callback');

// 8. 获取转换列表
$list = $convertio->getConversionList([
    'status' => 'finished',
    'count' => 5
]);

// 9. 取消转换
$cancel = $convertio->cancelConversion($conversionId);
*/
