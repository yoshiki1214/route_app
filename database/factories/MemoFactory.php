<?php

namespace Database\Factories;

use App\Models\Memo;
use App\Models\Client;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class MemoFactory extends Factory
{
    protected $model = Memo::class;

    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'user_id' => User::factory(),
            'title' => $this->faker->sentence(),
            'content' => $this->faker->realText(500),
            'category' => $this->faker->randomElement(['一般', '重要', '契約', '商談', '要フォロー']),
            'is_important' => $this->faker->boolean(20),  // 20%の確率で重要フラグを立てる
            'reminder_at' => $this->faker->optional(30)->dateTimeBetween('now', '+3 months'),  // 30%の確率でリマインダーを設定
        ];
    }
}
