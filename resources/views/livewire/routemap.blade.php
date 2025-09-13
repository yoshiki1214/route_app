<?php

use function Livewire\Volt\{state};
use App\Models\Client;
use App\Services\RouteOptimizer;

state([
    'clients' => fn() => Client::all(),
    'selectedClients' => [],
    'optimizedRoute' => [],
    'totalDistance' => 0,
    'totalDuration' => 0,
]);

$optimizeRoute = function () {
    if (count($this->selectedClients) < 2) {
        return;
    }

    // 選択された顧客の位置情報を取得
    $waypoints = collect($this->selectedClients)
        ->map(function ($clientId) {
            $client = Client::find($clientId);
            return [
                'id' => $client->id,
                'name' => $client->name,
                'lat' => $client->latitude,
                'lng' => $client->longitude,
            ];
        })
        ->all();

    // RouteOptimizerを使用してルートを最適化
    $optimizer = new RouteOptimizer();
    $result = $optimizer->optimize($waypoints);

    $this->optimizedRoute = $result['route'];
    $this->totalDistance = $result['distance'];
    $this->totalDuration = $result['duration'];
};

$generateGoogleMapsUrl = function () {
    if (empty($this->optimizedRoute)) {
        return '';
    }

    $origin = $this->optimizedRoute[0];
    $destination = end($this->optimizedRoute);
    $waypoints = array_slice($this->optimizedRoute, 1, -1);

    $url = 'https://www.google.com/maps/dir/?api=1';
    $url .= '&origin=' . $origin['lat'] . ',' . $origin['lng'];
    $url .= '&destination=' . $destination['lat'] . ',' . $destination['lng'];

    if (!empty($waypoints)) {
        $waypointStr = collect($waypoints)
            ->map(function ($point) {
                return $point['lat'] . ',' . $point['lng'];
            })
            ->join('|');
        $url .= '&waypoints=' . urlencode($waypointStr);
    }

    return $url;
};

?>

<div class="p-4">
    <div class="mb-6">
        <h2 class="text-xl font-bold mb-4">訪問ルート最適化</h2>

        <!-- 顧客選択 -->
        <div class="mb-4">
            <h3 class="text-lg font-semibold mb-2">訪問先を選択</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach ($clients as $client)
                    <label class="flex items-center space-x-2">
                        <input type="checkbox" wire:model.live="selectedClients" value="{{ $client->id }}"
                            class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                        <span>{{ $client->name }}</span>
                    </label>
                @endforeach
            </div>
        </div>

        <!-- ルート最適化ボタン -->
        <button wire:click="optimizeRoute" @class([
            'px-4 py-2 rounded',
            'bg-blue-600 text-white hover:bg-blue-700' => count($selectedClients) >= 2,
            'bg-gray-300 cursor-not-allowed' => count($selectedClients) < 2,
        ]) @disabled(count($selectedClients) < 2)>
            ルートを最適化
        </button>
    </div>

    <!-- 最適化されたルート表示 -->
    @if (!empty($optimizedRoute))
        <div class="mb-6">
            <h3 class="text-lg font-semibold mb-2">最適化されたルート</h3>
            <div class="bg-white rounded-lg shadow p-4">
                <div class="space-y-2">
                    @foreach ($optimizedRoute as $index => $point)
                        <div class="flex items-center">
                            <span
                                class="w-8 h-8 flex items-center justify-center rounded-full bg-blue-100 text-blue-800 font-semibold">
                                {{ $index + 1 }}
                            </span>
                            <span class="ml-3">{{ $point['name'] }}</span>
                        </div>
                        @if (!$loop->last)
                            <div class="w-0.5 h-4 bg-gray-300 ml-4"></div>
                        @endif
                    @endforeach
                </div>

                <div class="mt-4 pt-4 border-t border-gray-200">
                    <div class="text-sm text-gray-600">
                        <p>予想総距離: {{ number_format($totalDistance / 1000, 1) }} km</p>
                        <p>予想所要時間: {{ number_format($totalDuration / 60, 0) }} 分</p>
                    </div>
                </div>

                <div class="mt-4">
                    <a href="{{ $this->generateGoogleMapsUrl() }}" target="_blank"
                        class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700">
                        <span>Googleマップで開く</span>
                    </a>
                </div>
            </div>
        </div>
    @endif
</div>
