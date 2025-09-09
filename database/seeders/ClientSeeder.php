<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Client;

class ClientSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 実在する会社のサンプルデータ
        $realCompanies = [
            [
                'name' => '株式会社A商事',
                'address' => '東京都千代田区丸の内1-1-1',
                'latitude' => 35.681236,
                'longitude' => 139.767125,
                'phone' => '03-1234-5678',
                'email' => 'info@a-shoji.example.com',
                'contact_person' => '山田 一郎',
                'department' => '営業部',
                'position' => '部長',
                'notes' => '大手商社。年間契約あり。',
            ],
            [
                'name' => 'B工業株式会社',
                'address' => '東京都大田区蒲田5-1-1',
                'latitude' => 35.562222,
                'longitude' => 139.716389,
                'phone' => '03-2345-6789',
                'email' => 'contact@b-kogyo.example.com',
                'contact_person' => '鈴木 次郎',
                'department' => '製造部',
                'position' => '課長',
                'notes' => '製造業。月次定期訪問。',
            ],
            [
                'name' => 'C物流株式会社',
                'address' => '東京都江東区豊洲6-1-1',
                'latitude' => 35.645736,
                'longitude' => 139.792706,
                'phone' => '03-3456-7890',
                'email' => 'support@c-logistics.example.com',
                'contact_person' => '佐藤 三郎',
                'department' => '物流管理部',
                'position' => '主任',
                'notes' => '物流会社。新規取引開始。',
            ],
        ];

        // 実在する会社データを作成
        foreach ($realCompanies as $company) {
            Client::create($company);
        }

        // ランダムな会社データを追加で作成
        Client::factory(17)->create();
    }
}
