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
        Schema::create('memos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('title');                   // メモタイトル
            $table->text('content');                   // メモ内容
            $table->string('category')->nullable();    // メモのカテゴリ
            $table->boolean('is_important')->default(false); // 重要フラグ
            $table->timestamp('reminder_at')->nullable(); // リマインダー日時
            $table->timestamps();
            $table->softDeletes();                     // 論理削除用
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('memos');
    }
};
