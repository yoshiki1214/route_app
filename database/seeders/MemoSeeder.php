<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Memo;
use App\Models\Client;
use App\Models\User;
use Carbon\Carbon;

class MemoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $clients = Client::all();
        $users = User::all();

        // 実在する会社のメモを作成
        $clients->take(3)->each(function ($client) use ($users) {
            // 重要なメモ
            Memo::create([
                'client_id' => $client->id,
                'user_id' => $users->random()->id,
                'title' => '年間契約の更新について',
                'content' => "・現在の契約期間：2024年3月まで\n・更新時期：2024年2月\n・更新のポイント：価格改定の可能性あり",
                'category' => '契約',
                'is_important' => true,
                'reminder_at' => Carbon::now()->addMonths(1),
            ]);

            // 通常のメモ
            $memoContents = [
                '担当者の情報' => "・趣味：ゴルフ\n・好きな店：〇〇\n・記念日：7月7日",
                '商談履歴' => "・2023年12月：新規案件の相談\n・2024年1月：見積もり提出\n・2024年2月：契約締結",
                '要フォロー事項' => "・新製品の案内待ち\n・価格改定の検討\n・技術サポートの要望",
            ];

            foreach ($memoContents as $title => $content) {
                Memo::create([
                    'client_id' => $client->id,
                    'user_id' => $users->random()->id,
                    'title' => $title,
                    'content' => $content,
                    'category' => ['一般', '商談', '要フォロー'][rand(0, 2)],
                    'is_important' => false,
                    'reminder_at' => rand(0, 1) ? Carbon::now()->addDays(rand(1, 30)) : null,
                ]);
            }
        });

        // その他のクライアントにランダムなメモを作成
        $clients->slice(3)->each(function ($client) use ($users) {
            $memoCount = rand(2, 5);
            Memo::factory($memoCount)->create([
                'client_id' => $client->id,
                'user_id' => $users->random()->id,
            ]);
        });
    }
}
