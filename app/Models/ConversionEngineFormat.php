<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConversionEngineFormat extends Model
{
    use HasFactory;

    protected $table = 'conversion_engine_formats';

    protected $fillable = [
        'input_format',
        'output_format',
        'default_engine',
    ];

    protected $casts = [
        'input_format' => 'string',
        'output_format' => 'string',
        'default_engine' => 'string',
    ];

    /**
     * 获取格式组合的显示名称
     */
    public function getFormatCombinationAttribute(): string
    {
        return strtoupper($this->input_format) . ' → ' . strtoupper($this->output_format);
    }

    /**
     * 检查是否为有效的格式组合
     */
    public function isValidFormatCombination(): bool
    {
        return !empty($this->input_format) && !empty($this->output_format) && $this->input_format !== $this->output_format;
    }

    /**
     * 获取支持的引擎列表
     */
    public static function getSupportedEngines(): array
    {
        return [
            'convertio' => 'Convertio',
            'cloudconvert' => 'CloudConvert',
            'imagemagick' => 'ImageMagick',
            'libreoffice' => 'LibreOffice',
            'ffmpeg' => 'FFmpeg',
        ];
    }

    /**
     * 根据输入和输出格式查找默认引擎
     */
    public static function findDefaultEngine(string $inputFormat, string $outputFormat): ?string
    {
        $format = static::where('input_format', $inputFormat)
            ->where('output_format', $outputFormat)
            ->first();

        return $format?->default_engine;
    }
}
