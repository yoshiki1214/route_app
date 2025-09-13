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
    'travelMode' => '',
    'useTollRoads' => false,
    'useHighways' => false,
    'mapApiKey' => '',
]);

$mount = function () {
    $user = auth()->user();
    $this->travelMode = $user->travel_mode;
    $this->useTollRoads = $user->use_toll_roads;
    $this->useHighways = $user->use_highways;
    $this->mapApiKey = config('services.google_maps.api_key');
};

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
    $this->travelMode = $result['travel_mode'];
    $this->useTollRoads = $result['use_toll_roads'];
    $this->useHighways = $result['use_highways'];
};

$generateGoogleMapsUrl = function () {
    if (empty($this->optimizedRoute)) {
        return '';
    }

    $origin = $this->optimizedRoute[0];
    $destination = end($this->optimizedRoute);
    $waypoints = array_slice($this->optimizedRoute, 1, -1);

    $params = [
        'api' => 1,
        'origin' => $origin['lat'] . ',' . $origin['lng'],
        'destination' => $destination['lat'] . ',' . $destination['lng'],
        'travelmode' => $this->travelMode,
    ];

    if (!empty($waypoints)) {
        $waypointStr = collect($waypoints)
            ->map(function ($point) {
                return $point['lat'] . ',' . $point['lng'];
            })
            ->join('|');
        $params['waypoints'] = $waypointStr;
    }

    // 有料道路と高速道路の設定を追加
    $avoid = [];
    if (!$this->useTollRoads) {
        $avoid[] = 'tolls';
    }
    if (!$this->useHighways) {
        $avoid[] = 'highways';
    }
    if (!empty($avoid)) {
        $params['avoid'] = implode('|', $avoid);
    }

    // パラメータを安全にエンコード
    $queryString = http_build_query($params);
    return 'https://www.google.com/maps/dir/?' . $queryString;
};

$getTravelModeText = function () {
    return match ($this->travelMode) {
        'driving' => '車',
        'walking' => '徒歩',
        'transit' => '公共交通機関',
        default => '車',
    };
};

?>

<div class="p-4">
    <div class="mb-6">
        <h2 class="text-xl font-bold mb-4">訪問ルート最適化</h2>

        <!-- 現在の移動設定の表示 -->
        <div class="mb-4 p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
            <h3 class="text-lg font-semibold mb-2">現在の移動設定</h3>
            <div class="space-y-2">
                <p>移動手段: {{ $this->getTravelModeText() }}</p>
                @if ($travelMode === 'driving')
                    <p>有料道路: {{ $useTollRoads ? '使用する' : '使用しない' }}</p>
                    <p>高速道路: {{ $useHighways ? '使用する' : '使用しない' }}</p>
                @endif
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    ※ 設定は「設定 > ルート設定」から変更できます
                </p>
            </div>
        </div>

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
                <!-- Google Maps表示 -->
                <div class="mb-4 h-96 rounded-lg overflow-hidden">
                    <div id="map" class="w-full h-full"></div>
                </div>

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
                        <p class="mt-2 text-xs text-gray-500">
                            ※ 交通状況や道路状況により、実際の所要時間は変動する場合があります
                        </p>
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

<!-- Google Maps JavaScript -->
@script
    <script>
        let map;
        let directionsService;
        let directionsRenderer;

        function initMap() {
            // Google Maps APIの初期化
            map = new google.maps.Map(document.getElementById('map'), {
                zoom: 12,
                center: {
                    lat: 35.6812,
                    lng: 139.7671
                }, // デフォルトは東京
            });

            directionsService = new google.maps.DirectionsService();
            directionsRenderer = new google.maps.DirectionsRenderer({
                map: map,
                suppressMarkers: false,
            });

            // ルートが存在する場合は表示
            if ($wire.optimizedRoute.length > 0) {
                displayRoute();
            }
        }

        function displayRoute() {
            const waypoints = $wire.optimizedRoute.slice(1, -1).map(point => ({
                location: new google.maps.LatLng(point.lat, point.lng),
                stopover: true
            }));

            const origin = $wire.optimizedRoute[0];
            const destination = $wire.optimizedRoute[$wire.optimizedRoute.length - 1];

            // 回避オプションの設定
            const avoidOptions = [];
            if (!$wire.useTollRoads) avoidOptions.push('tolls');
            if (!$wire.useHighways) avoidOptions.push('highways');

            const request = {
                origin: new google.maps.LatLng(origin.lat, origin.lng),
                destination: new google.maps.LatLng(destination.lat, destination.lng),
                waypoints: waypoints,
                travelMode: google.maps.TravelMode[$wire.travelMode.toUpperCase()],
                avoidTolls: !$wire.useTollRoads,
                avoidHighways: !$wire.useHighways,
                optimizeWaypoints: true,
            };

            directionsService.route(request, (result, status) => {
                if (status === 'OK') {
                    directionsRenderer.setDirections(result);
                } else {
                    // エラーメッセージをユーザーに表示
                    const errorMessages = {
                        'NOT_FOUND': '指定された場所が見つかりませんでした。',
                        'ZERO_RESULTS': '経路が見つかりませんでした。移動手段や経路設定を変更してみてください。',
                        'MAX_WAYPOINTS_EXCEEDED': '経由地点が多すぎます。',
                        'MAX_ROUTE_LENGTH_EXCEEDED': 'ルートが長すぎます。',
                        'INVALID_REQUEST': '無効なリクエストです。',
                        'OVER_QUERY_LIMIT': 'クエリ制限を超えました。しばらく待ってから再試行してください。',
                        'REQUEST_DENIED': 'リクエストが拒否されました。',
                        'UNKNOWN_ERROR': '不明なエラーが発生しました。'
                    };

                    const errorMessage = errorMessages[status] || 'ルートの取得中にエラーが発生しました。';
                    console.error('Directions request failed:', status);

                    // エラーメッセージを表示
                    const errorDiv = document.createElement('div');
                    errorDiv.className =
                        'bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4';
                    errorDiv.innerHTML = `
                        <strong class="font-bold">エラー:</strong>
                        <span class="block sm:inline">${errorMessage}</span>
                    `;

                    const mapDiv = document.getElementById('map');
                    mapDiv.parentNode.insertBefore(errorDiv, mapDiv);

                    // 5秒後にエラーメッセージを消す
                    setTimeout(() => {
                        errorDiv.remove();
                    }, 5000);
                }
            });
        }

        // ルートが更新されたときに地図を更新
        $wire.on('routeUpdated', () => {
            if ($wire.optimizedRoute.length > 0) {
                displayRoute();
            }
        });
    </script>
@endscript

<!-- Google Maps API読み込み -->
@if ($mapApiKey)
    <script async defer
        src="https://maps.googleapis.com/maps/api/js?key={{ $mapApiKey }}&callback=initMap&libraries=places"></script>
@endif
