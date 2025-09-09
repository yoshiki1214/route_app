<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Client;
use App\Models\Visit;
use App\Models\Memo;

class DatabaseSeeder extends Seeder
{
    /**
     * データベースシードを実行
     */
    public function run(): void
    {
        // 管理者ユーザーを作成
        $admin = User::factory()->create([
            'name' => '管理者',
            'email' => 'admin@example.com',
        ]);

        // 営業担当者を5名作成
        $salesUsers = User::factory(5)->create();
        $allUsers = $salesUsers->push($admin);

        // 取引先を20社作成
        $clients = Client::factory(20)->create();

        // 各取引先に対して訪問履歴を作成（過去6ヶ月分）
        $clients->each(function ($client) use ($allUsers) {
            // 1社あたり3〜10件の訪問履歴
            $visitCount = rand(3, 10);
            Visit::factory($visitCount)->create([
                'client_id' => $client->id,
                'user_id' => $allUsers->random()->id,
            ]);

            // 1社あたり5〜15件のメモ
            $memoCount = rand(5, 15);
            Memo::factory($memoCount)->create([
                'client_id' => $client->id,
                'user_id' => $allUsers->random()->id,
            ]);
        });
    }
}
