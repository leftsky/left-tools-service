<?php

namespace App\Console\Commands;

use App\Services\FFmpegService;
use Illuminate\Console\Command;

class TestFFmpegCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:ffmpeg';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '测试FFmpeg服务是否正常工作';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('开始测试FFmpeg服务...');

        $ffmpegService = app(FFmpegService::class);

        // 检查FFmpeg是否可用
        $this->info('检查FFmpeg是否可用...');
        $isAvailable = $ffmpegService->isFFmpegAvailable();
        if ($isAvailable) {
            $this->info('✅ FFmpeg可用');
            
            // 获取版本信息
            $version = $ffmpegService->getFFmpegVersion();
            if ($version) {
                $this->info("FFmpeg版本: {$version}");
            }
        } else {
            $this->error('❌ FFmpeg不可用');
            return 1;
        }

        // 显示支持的配置
        $this->info('获取支持的配置...');
        $configs = $ffmpegService->getSupportedConfigs();
        
        $this->info('支持的输出格式:');
        foreach ($configs['outputFormats'] as $format) {
            $this->line("  - {$format['value']}: {$format['label']}");
        }

        $this->info('视频质量选项:');
        foreach ($configs['videoQualityOptions'] as $option) {
            $this->line("  - {$option['value']}: {$option['label']}");
        }

        $this->info('分辨率选项:');
        foreach ($configs['resolutionOptions'] as $option) {
            $this->line("  - {$option['value']}: {$option['label']}");
        }

        $this->info('帧率选项:');
        foreach ($configs['framerateOptions'] as $option) {
            $this->line("  - {$option['value']}: {$option['label']}");
        }

        $this->info("最大文件大小: " . number_format($configs['maxFileSize'] / 1024 / 1024, 2) . " MB");

        // 测试转换选项验证
        $this->info('测试转换选项验证...');
        $testOptions = [
            'videoQuality' => 'high',
            'resolution' => '1080p',
            'framerate' => '30'
        ];

        $isValid = $ffmpegService->validateConversionOptions($testOptions);
        if ($isValid) {
            $this->info('✅ 转换选项验证通过');
        } else {
            $this->error('❌ 转换选项验证失败');
        }

        $this->info('FFmpeg服务测试完成！');
        return 0;
    }
}
