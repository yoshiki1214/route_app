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
        Schema::table('clients', function (Blueprint $table) {
            $table->string('department')->nullable()->after('contact_person');     // 部署
            $table->string('position')->nullable()->after('department');           // 役職
            $table->string('mobile_phone')->nullable()->after('phone');           // 携帯電話番号
            $table->string('fax')->nullable()->after('mobile_phone');             // FAX番号
            $table->text('notes')->nullable()->after('fax');                      // 備考
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn([
                'department',
                'position',
                'mobile_phone',
                'fax',
                'notes'
            ]);
        });
    }
};
