<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Bảng ai_logs: Lưu lịch sử hỏi đáp và kết quả phân tích kinh doanh của AI cho chủ cửa hàng.
     */
    public function up(): void
    {
        Schema::create('ai_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id')->index()->comment('ID cửa hàng thuộc về (Đảm bảo phân quyền cách ly AI dữ liệu)');
            $table->unsignedBigInteger('user_id')->index()->comment('ID người dùng thực hiện câu hỏi với AI');
            
            $table->text('question')->comment('Câu hỏi / Prompt từ chủ cửa hàng (Vd: "Mặt hàng nào bán chạy nhất tháng này?")');
            $table->longText('answer')->comment('Câu trả lời / Phản hồi tư vấn do AI phân tích và tạo ra');
            
            $table->string('type', 50)->default('general')->comment('Phân loại tác vụ AI: analysis (Phân tích doanh thu), restock (Tư vấn nhập hàng), slow_stock (Hàng tồn lâu), general (Hỏi đáp khác)');
            $table->integer('tokens_used')->nullable()->comment('Số lượng token tiêu tốn cho lượt gọi AI này');
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_logs');
    }
};
