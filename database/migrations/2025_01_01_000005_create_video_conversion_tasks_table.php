<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('video_conversion_tasks', function (Blueprint $table) {
            $table->comment('视频转换任务表');
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->comment('用户ID');

            // Convertio相关字段
            $table->string('convertio_id')->nullable()->unique()->comment('Convertio API任务ID');
            $table->string('input_method', 20)->default('url')->comment('输入方式: url/raw/base64/upload');
            $table->text('input_file')->comment('输入文件URL或内容');
            $table->string('filename')->comment('文件名');
            $table->unsignedBigInteger('file_size')->nullable()->comment('文件大小(字节)');
            $table->string('output_format', 20)->comment('输出格式');
            $table->json('conversion_options')->nullable()->comment('转换选项(OCR、回调等)');

            // 任务状态信息
            $table->tinyInteger('status')->default(0)->comment('任务状态: 0=等待中, 1=转换中, 2=已完成, 3=失败');
            $table->tinyInteger('step_percent')->default(0)->comment('进度百分比');
            $table->integer('minutes_used')->default(0)->comment('使用的分钟数');

            // 输出文件信息
            $table->string('output_url')->nullable()->comment('输出文件URL');
            $table->unsignedBigInteger('output_size')->nullable()->comment('输出文件大小(字节)');

            // 回调和其他信息
            $table->string('callback_url')->nullable()->comment('回调URL');
            $table->text('error_message')->nullable()->comment('错误信息');
            $table->timestamp('started_at')->nullable()->comment('开始时间');
            $table->timestamp('completed_at')->nullable()->comment('完成时间');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('video_conversion_tasks');
    }
};
