<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class RouteOptimizer
{
    private $apiKey;

    /**
     * Create a new class instance.
     */
    public function __construct()
    {
        $this->apiKey = config('services.google.maps_api_key');
    }

    /**
     * 最適なルートを計算
     *
     * @param array $waypoints 経由地点の配列 [['lat' => float, 'lng' => float, 'name' => string], ...]
     * @return array [
     *     'route' => array 最適化された順序の経由地点,
     *     'distance' => float 総距離(メートル),
     *     'duration' => float 総所要時間(秒)
     * ]
     */
    public function optimize(array $waypoints): array
    {
        if (count($waypoints) < 2) {
            return [
                'route' => $waypoints,
                'distance' => 0,
                'duration' => 0
            ];
        }

        // 最も近い地点を順に選んでいく単純な実装
        // 実際のプロダクションでは、Google Maps Distance Matrix APIを使用して
        // より正確な距離と時間を計算することを推奨
        $route = [];
        $remaining = $waypoints;
        $currentPoint = array_shift($remaining);
        $route[] = $currentPoint;
        $totalDistance = 0;
        $totalDuration = 0;

        while (!empty($remaining)) {
            $nextPoint = $this->findNearestPoint($currentPoint, $remaining);
            $distance = $this->calculateDistance(
                $currentPoint['lat'],
                $currentPoint['lng'],
                $nextPoint['lat'],
                $nextPoint['lng']
            );

            $totalDistance += $distance;
            // 仮の所要時間計算（平均速度40km/hと仮定）
            $totalDuration += ($distance / 40000) * 3600;

            $route[] = $nextPoint;
            $currentPoint = $nextPoint;
            $remaining = array_filter(
                $remaining,
                fn($point) =>
                $point['lat'] != $nextPoint['lat'] || $point['lng'] != $nextPoint['lng']
            );
        }

        return [
            'route' => $route,
            'distance' => $totalDistance,
            'duration' => $totalDuration
        ];
    }

    /**
     * 現在地から最も近い地点を見つける
     */
    private function findNearestPoint(array $current, array $points): array
    {
        $nearestPoint = null;
        $minDistance = PHP_FLOAT_MAX;

        foreach ($points as $point) {
            $distance = $this->calculateDistance(
                $current['lat'],
                $current['lng'],
                $point['lat'],
                $point['lng']
            );

            if ($distance < $minDistance) {
                $minDistance = $distance;
                $nearestPoint = $point;
            }
        }

        return $nearestPoint;
    }

    /**
     * 2点間の距離をメートル単位で計算（ヒュベニの公式）
     */
    private function calculateDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earth_radius = 6378137; // 地球の半径（メートル）

        $lat1 = deg2rad($lat1);
        $lng1 = deg2rad($lng1);
        $lat2 = deg2rad($lat2);
        $lng2 = deg2rad($lng2);

        $lat_diff = $lat1 - $lat2;
        $lng_diff = $lng1 - $lng2;
        $lat_avg = ($lat1 + $lat2) / 2;

        $x = $lng_diff * cos($lat_avg);
        $y = $lat_diff;

        return $earth_radius * sqrt($x * $x + $y * $y);
    }
}
