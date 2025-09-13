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
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('name');                    // 会社名/取引先名
            $table->string('address');                 // 住所
            $table->decimal('latitude', 10, 8);        // 緯度
            $table->decimal('longitude', 11, 8);       // 経度
            $table->string('phone')->nullable();       // 電話番号（任意）
            $table->string('email')->nullable();       // メールアドレス（任意）
            $table->string('contact_person')->nullable(); // 担当者名（任意）
            $table->timestamps();
            $table->softDeletes();                     // 論理削除用
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
