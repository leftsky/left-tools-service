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
        Schema::create('user_feedback', function (Blueprint $table) {
            $table->comment('用户反馈表');
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->comment('用户ID');
            $table->string('contact_phone')->nullable()->comment('联系电话');
            $table->tinyInteger('type')->comment('反馈类型：1-错误报告，2-功能建议，3-改进建议，4-其他');
            $table->string('title')->comment('反馈标题');
            $table->text('content')->comment('反馈内容');
            $table->json('attachments')->nullable()->comment('附件信息（JSON格式）');
            $table->tinyInteger('status')->default(1)->comment('处理状态：1-待处理，2-处理中，3-已解决，4-已关闭');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_feedback');
    }
};