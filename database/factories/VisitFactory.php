<?php

namespace Database\Factories;

use App\Models\Visit;
use App\Models\Client;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class VisitFactory extends Factory
{
    protected $model = Visit::class;

    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'user_id' => User::factory(),
            'visited_at' => $this->faker->dateTimeBetween('-6 months', 'now'),
            'latitude' => $this->faker->optional(0.7)->latitude(35.0, 36.0),  // 70%の確率で東京周辺の緯度
            'longitude' => $this->faker->optional(0.7)->longitude(139.0, 140.0),  // 70%の確率で東京周辺の経度
            'visit_type' => $this->faker->randomElement(['訪問', '電話', 'メール', 'オンライン会議', '展示会', 'その他']),
            'status' => $this->faker->randomElement(['完了', '予定', 'キャンセル', '延期']),
            'notes' => $this->faker->optional()->realText(200),
        ];
    }
}
