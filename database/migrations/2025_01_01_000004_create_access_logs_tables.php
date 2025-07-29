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
        // 1. 访问记录主表
        Schema::create('access_logs', function (Blueprint $table) {
            $table->comment('访问记录表');
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->comment('用户ID');
            $table->string('ip_address', 45)->comment('访问IP地址');
            $table->text('user_agent')->nullable()->comment('用户代理');
            $table->string('url')->comment('访问的URL路径');
            $table->string('referer')->nullable()->comment('来源页面');
            $table->string('session_id')->nullable()->comment('会话ID');
            $table->timestamps();

            // 索引
            $table->index('user_id');
            $table->index('ip_address');
            $table->index('created_at');
            $table->index('url');
        });

        // 2. 访问统计表
        Schema::create('access_stats', function (Blueprint $table) {
            $table->comment('访问统计表');
            $table->id();
            $table->date('date')->comment('统计日期');
            $table->string('url')->comment('访问的URL路径');
            $table->integer('visit_count')->default(0)->comment('访问次数');
            $table->integer('unique_visitors')->default(0)->comment('独立访客数');
            $table->timestamps();

            // 索引
            $table->index('date');
            $table->index('url');
            $table->unique(['date', 'url']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('access_stats');
        Schema::dropIfExists('access_logs');
    }
}; 