<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * データベースシードを実行
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,    // ユーザーを作成
            ClientSeeder::class,  // クライアントを作成
            VisitSeeder::class,   // 訪問履歴を作成
            MemoSeeder::class,    // メモを作成
        ]);
    }
}
