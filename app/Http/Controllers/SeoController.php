<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SeoController extends Controller
{
    /**
     * 获取页面的SEO信息
     */
    public function getPageSeo(Request $request): JsonResponse
    {
        $path = $request->path();
        $seoData = [];

        switch ($path) {
            case '':
            case '/':
                $seoData = [
                    'title' => '小左子的工具箱 - 专业在线工具服务平台 | 视频格式转换、抖音文案提取、音频处理',
                    'description' => '专业的在线工具服务平台，提供视频格式转换、抖音文案提取、音频处理、文档转换、图片处理等实用工具。支持MP4、AVI、MOV、MKV等多种视频格式转换，免费在线使用。',
                    'keywords' => '视频格式转换,在线视频转换器,MP4转换器,AVI转换器,MOV转换器,MKV转换器,视频转码工具,抖音文案提取,无水印视频下载,音频格式转换,文档转换,图片处理,在线工具,免费工具',
                    'og_title' => '小左子的工具箱 - 专业在线工具服务平台',
                    'og_description' => '提供视频格式转换、抖音文案提取、音频处理等实用工具，支持多种格式转换，免费在线使用。',
                    'canonical' => url('/'),
                ];
                break;

            case 'video-converter':
                $seoData = [
                    'title' => '视频格式转换工具 - 在线MP4、AVI、MOV、MKV转换器 | 小左子的工具箱',
                    'description' => '专业的在线视频格式转换器，支持MP4、AVI、MOV、MKV、WMV、FLV等多种格式转换。无需下载软件，免费在线使用，快速高效。',
                    'keywords' => '视频格式转换,MP4转换器,AVI转换器,MOV转换器,MKV转换器,WMV转换器,FLV转换器,在线视频转换,视频转码工具,免费视频转换',
                    'og_title' => '视频格式转换工具 - 在线MP4、AVI、MOV、MKV转换器',
                    'og_description' => '专业的在线视频格式转换器，支持多种格式转换，免费在线使用。',
                    'canonical' => url('/video-converter'),
                ];
                break;

            case 'tools':
                $seoData = [
                    'title' => '在线工具集合 - 视频转换、文案提取、音频处理 | 小左子的工具箱',
                    'description' => '提供多种实用在线工具，包括视频格式转换、抖音文案提取、音频处理、文档转换、图片处理等，满足您的不同需求。',
                    'keywords' => '在线工具,视频转换,文案提取,音频处理,文档转换,图片处理,免费工具,实用工具',
                    'og_title' => '在线工具集合 - 视频转换、文案提取、音频处理',
                    'og_description' => '提供多种实用在线工具，满足您的不同需求。',
                    'canonical' => url('/tools'),
                ];
                break;

            case 'about':
                $seoData = [
                    'title' => '关于我们 - 小左子的工具箱 | 专业在线工具服务平台',
                    'description' => '小左子的工具箱致力于为用户提供高质量、高效率的在线工具服务，包括视频格式转换、抖音文案提取、音频处理等，让技术更好地服务于生活。',
                    'keywords' => '关于我们,小左子的工具箱,在线工具,视频转换,文案提取,音频处理',
                    'og_title' => '关于我们 - 小左子的工具箱',
                    'og_description' => '致力于为用户提供高质量、高效率的在线工具服务。',
                    'canonical' => url('/about'),
                ];
                break;

            default:
                $seoData = [
                    'title' => '小左子的工具箱 - 专业在线工具服务平台',
                    'description' => '专业的在线工具服务平台，提供视频格式转换、抖音文案提取、音频处理等实用工具。',
                    'keywords' => '在线工具,视频转换,文案提取,音频处理,免费工具',
                    'og_title' => '小左子的工具箱 - 专业在线工具服务平台',
                    'og_description' => '提供多种实用在线工具，免费在线使用。',
                    'canonical' => url($path),
                ];
        }

        return response()->json($seoData);
    }

    /**
     * 获取结构化数据
     */
    public function getStructuredData(Request $request): JsonResponse
    {
        $path = $request->path();
        $structuredData = [];

        switch ($path) {
            case '':
            case '/':
                $structuredData = [
                    '@context' => 'https://schema.org',
                    '@type' => 'WebApplication',
                    'name' => '小左子的工具箱',
                    'description' => '专业的在线工具服务平台，提供视频格式转换、抖音文案提取、音频处理、文档转换、图片处理等实用工具',
                    'url' => url('/'),
                    'applicationCategory' => '工具软件',
                    'operatingSystem' => 'Web',
                    'offers' => [
                        '@type' => 'Offer',
                        'price' => '0',
                        'priceCurrency' => 'CNY'
                    ],
                    'featureList' => [
                        '视频格式转换',
                        '抖音文案提取', 
                        '音频处理',
                        '文档转换',
                        '图片处理',
                        '数据分析'
                    ],
                    'author' => [
                        '@type' => 'Organization',
                        'name' => '小左子的工具箱'
                    ]
                ];
                break;

            case 'video-converter':
                $structuredData = [
                    '@context' => 'https://schema.org',
                    '@type' => 'WebApplication',
                    'name' => '视频格式转换工具',
                    'description' => '专业的在线视频格式转换器，支持MP4、AVI、MOV、MKV等多种格式转换',
                    'url' => url('/video-converter'),
                    'applicationCategory' => '多媒体工具',
                    'operatingSystem' => 'Web',
                    'offers' => [
                        '@type' => 'Offer',
                        'price' => '0',
                        'priceCurrency' => 'CNY'
                    ],
                    'featureList' => [
                        'MP4格式转换',
                        'AVI格式转换',
                        'MOV格式转换',
                        'MKV格式转换',
                        'WMV格式转换',
                        'FLV格式转换'
                    ],
                    'author' => [
                        '@type' => 'Organization',
                        'name' => '小左子的工具箱'
                    ]
                ];
                break;

            default:
                $structuredData = [
                    '@context' => 'https://schema.org',
                    '@type' => 'WebPage',
                    'name' => '小左子的工具箱',
                    'description' => '专业的在线工具服务平台',
                    'url' => url($path)
                ];
        }

        return response()->json($structuredData);
    }
} 