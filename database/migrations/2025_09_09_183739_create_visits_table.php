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
        Schema::create('visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->timestamp('visited_at');           // 訪問日時
            $table->decimal('latitude', 10, 8);        // 訪問時の緯度
            $table->decimal('longitude', 11, 8);       // 訪問時の経度
            $table->string('visit_type')->default('in_person'); // 訪問種別（対面/オンライン等）
            $table->string('status')->default('completed'); // 訪問状態（完了/キャンセル等）
            $table->text('notes')->nullable();         // 訪問時の簡単なメモ
            $table->timestamps();
            $table->softDeletes();                     // 論理削除用
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('visits');
    }
};
