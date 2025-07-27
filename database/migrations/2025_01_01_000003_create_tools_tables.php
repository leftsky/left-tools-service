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
        // 1. 工具表
        Schema::create('tools', function (Blueprint $table) {
            $table->comment('工具信息表');
            $table->id();
            $table->string('name')->comment('工具名称');
            $table->boolean('is_enabled')->default(true)->comment('是否启用');
            $table->integer('sort_weight')->default(0)->comment('排序权重');
            $table->integer('hotness')->default(0)->comment('热度');
            $table->timestamps();
        });

        // 2. 工具使用记录表
        Schema::create('tool_usage_logs', function (Blueprint $table) {
            $table->comment('工具使用记录表');
            $table->id();
            $table->unsignedBigInteger('tool_id')->comment('工具ID');
            $table->unsignedBigInteger('user_id')->nullable()->comment('用户ID');
            $table->timestamp('used_at')->comment('使用时间');
            $table->timestamps();
        });

        // 3. 工具使用次数统计表
        Schema::create('tool_usage_stats', function (Blueprint $table) {
            $table->comment('工具使用次数统计表');
            $table->id();
            $table->unsignedBigInteger('tool_id')->comment('工具ID');
            $table->date('date')->comment('统计日期');
            $table->integer('usage_count')->default(0)->comment('使用次数');
            $table->integer('user_count')->default(0)->comment('使用人数');
            $table->timestamps();
        });

        // 4. 用户收藏工具表
        Schema::create('user_tool_favorites', function (Blueprint $table) {
            $table->comment('用户收藏工具表');
            $table->id();
            $table->unsignedBigInteger('user_id')->comment('用户ID');
            $table->unsignedBigInteger('tool_id')->comment('工具ID');
            $table->integer('weight')->default(0)->comment('权重');
            $table->timestamps();
        });

        // 5. 用户历史使用工具表
        Schema::create('user_tool_history', function (Blueprint $table) {
            $table->comment('用户历史使用工具表');
            $table->id();
            $table->unsignedBigInteger('user_id')->comment('用户ID');
            $table->unsignedBigInteger('tool_id')->comment('工具ID');
            $table->timestamp('last_used_at')->comment('最后使用时间');
            $table->integer('usage_count')->default(1)->comment('使用次数');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_tool_history');
        Schema::dropIfExists('user_tool_favorites');
        Schema::dropIfExists('tool_usage_stats');
        Schema::dropIfExists('tool_usage_logs');
        Schema::dropIfExists('tools');
    }
};
