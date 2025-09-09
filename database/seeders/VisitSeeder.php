<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Visit;
use App\Models\Client;
use App\Models\User;
use Carbon\Carbon;

class VisitSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $clients = Client::all();
        $users = User::all();

        // 実在する会社の訪問履歴を作成
        $clients->take(3)->each(function ($client) use ($users) {
            // 過去の訪問履歴
            for ($i = 1; $i <= 5; $i++) {
                Visit::create([
                    'client_id' => $client->id,
                    'user_id' => $users->random()->id,
                    'visited_at' => Carbon::now()->subMonths($i)->addDays(rand(-5, 5)),
                    'latitude' => rand(0, 1) ? $client->latitude + (rand(-10, 10) / 1000) : null,  // 50%の確率でnull
                    'longitude' => rand(0, 1) ? $client->longitude + (rand(-10, 10) / 1000) : null,  // 50%の確率でnull
                    'visit_type' => ['訪問', '電話', 'オンライン会議', 'その他'][rand(0, 3)],
                    'status' => '完了',
                    'notes' => "訪問記録 #{$i}回目。商談実施。",
                ]);
            }

            // 予定されている訪問
            for ($i = 1; $i <= 2; $i++) {
                Visit::create([
                    'client_id' => $client->id,
                    'user_id' => $users->random()->id,
                    'visited_at' => Carbon::now()->addDays($i * 7),
                    'latitude' => null,  // 予定の訪問は位置情報なし
                    'longitude' => null,  // 予定の訪問は位置情報なし
                    'visit_type' => ['訪問', '電話', 'オンライン会議'][rand(0, 2)],
                    'status' => '予定',
                    'notes' => "次回の訪問予定。",
                ]);
            }
        });

        // その他のクライアントにランダムな訪問履歴を作成
        $clients->slice(3)->each(function ($client) use ($users) {
            $visitCount = rand(3, 10);
            Visit::factory($visitCount)->create([
                'client_id' => $client->id,
                'user_id' => $users->random()->id,
            ]);
        });
    }
}
