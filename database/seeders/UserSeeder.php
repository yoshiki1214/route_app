<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 管理者ユーザーを作成
        User::factory()->create([
            'name' => '管理者',
            'email' => 'admin@example.com',
        ]);

        // 営業担当者を作成
        $salesUsers = collect([
            [
                'name' => '営業 太郎',
                'email' => 'sales1@example.com',
            ],
            [
                'name' => '営業 次郎',
                'email' => 'sales2@example.com',
            ],
            [
                'name' => '営業 三郎',
                'email' => 'sales3@example.com',
            ],
            [
                'name' => '営業 四郎',
                'email' => 'sales4@example.com',
            ],
            [
                'name' => '営業 五郎',
                'email' => 'sales5@example.com',
            ],
        ]);

        $salesUsers->each(function ($user) {
            User::factory()->create($user);
        });
    }
}
