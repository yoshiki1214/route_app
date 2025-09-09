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
            'latitude' => $this->faker->latitude(35.0, 36.0),  // 東京周辺
            'longitude' => $this->faker->longitude(139.0, 140.0),  // 東京周辺
            'visit_type' => $this->faker->randomElement(['定期訪問', '商談', '契約', '緊急対応', 'その他']),
            'status' => $this->faker->randomElement(['予定', '完了', 'キャンセル', '延期']),
            'notes' => $this->faker->optional()->realText(200),
        ];
    }
}
