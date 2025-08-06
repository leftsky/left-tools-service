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
            
            // 文件路径
            $table->string('input_file_path')->comment('输入文件路径');
            $table->string('output_file_path')->comment('输出文件路径');
            
            // 文件信息（JSON格式存储）
            $table->string('input_file_info', 512)->nullable()->comment('输入文件信息(JSON格式)');
            $table->string('output_file_info', 512)->nullable()->comment('输出文件信息(JSON格式)');
            
            // 转换参数
            $table->string('conversion_params', 512)->nullable()->comment('转换参数(JSON格式)');
            
            // 任务状态信息
            $table->tinyInteger('status')->default(0)->comment('任务状态: 0=等待中, 1=处理中, 2=已完成, 3=失败, 4=已取消');
            $table->string('job_id')->nullable()->comment('队列任务ID');
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
