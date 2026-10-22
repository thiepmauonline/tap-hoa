<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_logs', function (Blueprint $table) {
            $table->string('provider', 20)->nullable()->comment('Nguồn trả lời: gemini, openai hoặc local (ngoại tuyến)');
            $table->string('model', 100)->nullable()->comment('Tên mô hình AI đã trả lời');
            $table->json('tools_used')->nullable()->comment('Các công cụ truy vấn dữ liệu AI đã gọi');
        });
    }

    public function down(): void
    {
        Schema::table('ai_logs', function (Blueprint $table) {
            $table->dropColumn(['provider', 'model', 'tools_used']);
        });
    }
};
