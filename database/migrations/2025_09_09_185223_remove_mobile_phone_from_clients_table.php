<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            // 既存のmobile_phoneの値をphoneに移行（phoneが空の場合のみ）
            DB::statement("
                UPDATE clients 
                SET phone = mobile_phone 
                WHERE phone IS NULL AND mobile_phone IS NOT NULL
            ");

            $table->dropColumn('mobile_phone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('mobile_phone')->nullable()->after('phone');
        });
    }
};
