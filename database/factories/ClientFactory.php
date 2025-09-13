<?php

namespace Database\Factories;

use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

class ClientFactory extends Factory
{
    protected $model = Client::class;

    public function definition(): array
    {
        // 日本の緯度経度の範囲内でランダムな位置を生成
        $latitude = $this->faker->latitude(35.0, 36.0);  // 東京周辺
        $longitude = $this->faker->longitude(139.0, 140.0);  // 東京周辺

        return [
            'name' => $this->faker->company(),
            'address' => $this->faker->address(),
            'latitude' => $latitude,
            'longitude' => $longitude,
            'phone' => $this->faker->phoneNumber(),
            'email' => $this->faker->companyEmail(),
            'contact_person' => $this->faker->name(),
            'department' => $this->faker->randomElement(['営業部', '総務部', '人事部', '経理部', '開発部']),
            'position' => $this->faker->randomElement(['部長', '課長', '主任', '担当']),
            'notes' => $this->faker->optional()->realText(200),
        ];
    }
}
