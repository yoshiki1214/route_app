<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class RouteOptimizer
{
    private $apiKey;

    public function __construct()
    {
        $this->apiKey = config('services.google.maps_api_key');
    }

    public function optimize(array $waypoints): array
    {
        if (count($waypoints) < 2) {
            return [
                'route' => $waypoints,
                'distance' => 0,
                'duration' => 0
            ];
        }

        // ユーザーの移動設定を取得
        $user = Auth::user();
        $travelMode = $user->travel_mode ?? 'driving';
        $useTollRoads = $user->use_toll_roads ?? true;
        $useHighways = $user->use_highways ?? true;

        // 最適な順序を計算
        $optimizedRoute = $this->calculateOptimalRoute($waypoints, $travelMode, $useTollRoads, $useHighways);

        return [
            'route' => $optimizedRoute['waypoints'],
            'distance' => $optimizedRoute['total_distance'],
            'duration' => $optimizedRoute['total_duration'],
            'travel_mode' => $travelMode,
            'use_toll_roads' => $useTollRoads,
            'use_highways' => $useHighways
        ];
    }

    private function calculateOptimalRoute(array $waypoints, string $travelMode, bool $useTollRoads, bool $useHighways): array
    {
        $origin = array_shift($waypoints);
        $destination = array_pop($waypoints);
        $optimizedWaypoints = [$origin];
        $totalDistance = 0;
        $totalDuration = 0;

        // 中間地点の最適化
        while (!empty($waypoints)) {
            $nextPoint = null;
            $minTotalTime = PHP_FLOAT_MAX;
            $selectedDistance = 0;
            $selectedDuration = 0;

            foreach ($waypoints as $index => $waypoint) {
                $result = $this->getDirectionsData(
                    $optimizedWaypoints[count($optimizedWaypoints) - 1],
                    $waypoint,
                    $travelMode,
                    $useTollRoads,
                    $useHighways
                );

                if ($result && $result['duration'] < $minTotalTime) {
                    $minTotalTime = $result['duration'];
                    $nextPoint = $waypoint;
                    $selectedDistance = $result['distance'];
                    $selectedDuration = $result['duration'];
                }
            }

            if ($nextPoint) {
                $optimizedWaypoints[] = $nextPoint;
                $totalDistance += $selectedDistance;
                $totalDuration += $selectedDuration;

                // 選択された地点を配列から削除
                $waypoints = array_filter($waypoints, function ($point) use ($nextPoint) {
                    return $point['lat'] != $nextPoint['lat'] || $point['lng'] != $nextPoint['lng'];
                });
            }
        }

        // 最後の目的地を追加
        $optimizedWaypoints[] = $destination;
        $finalLeg = $this->getDirectionsData(
            $optimizedWaypoints[count($optimizedWaypoints) - 2],
            $destination,
            $travelMode,
            $useTollRoads,
            $useHighways
        );

        if ($finalLeg) {
            $totalDistance += $finalLeg['distance'];
            $totalDuration += $finalLeg['duration'];
        }

        return [
            'waypoints' => $optimizedWaypoints,
            'total_distance' => $totalDistance,
            'total_duration' => $totalDuration
        ];
    }

    private function getDirectionsData(array $origin, array $destination, string $travelMode, bool $useTollRoads, bool $useHighways): ?array
    {
        $avoid = [];
        if (!$useTollRoads) {
            $avoid[] = 'tolls';
        }
        if (!$useHighways) {
            $avoid[] = 'highways';
        }

        $url = "https://maps.googleapis.com/maps/api/directions/json";
        $response = Http::get($url, [
            'origin' => "{$origin['lat']},{$origin['lng']}",
            'destination' => "{$destination['lat']},{$destination['lng']}",
            'mode' => $travelMode,
            'avoid' => implode('|', $avoid),
            'key' => $this->apiKey,
            'language' => 'ja',
            'units' => 'metric'
        ]);

        $data = $response->json();

        if ($response->successful() && isset($data['routes'][0])) {
            $route = $data['routes'][0];
            $leg = $route['legs'][0];

            return [
                'distance' => $leg['distance']['value'], // メートル単位
                'duration' => $leg['duration']['value'], // 秒単位
                'distance_text' => $leg['distance']['text'],
                'duration_text' => $leg['duration']['text']
            ];
        }

        Log::error('Google Directions API error', [
            'response' => $data,
            'origin' => $origin,
            'destination' => $destination
        ]);

        return null;
    }
}
