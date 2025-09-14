<?php

use function Livewire\Volt\{state, computed, on};
use App\Models\Client;
use Carbon\Carbon;

state([
    'clients' => fn() => Client::with([
        'visits' => function ($query) {
            $query->latest('visited_at');
        },
    ])->get(),
    'search' => '',
    'sortField' => 'last_visit_desc',
    'sortDirection' => 'asc',
    'currentLocation' => [
        'latitude' => null,
        'longitude' => null,
    ],
    'isLoadingLocation' => false,
    'clientDistances' => [],
]);

$sortBy = function ($field) {
    if ($this->sortField === $field) {
        $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
    } else {
        $this->sortField = $field;
        $this->sortDirection = 'asc';
    }
};

$sortByDistance = function () {
    \Log::info('Starting distance sort - requesting current location');
    $this->isLoadingLocation = true;
    $this->dispatch('get-current-location');
};

$setCurrentLocation = function ($latitude, $longitude) {
    \Log::info('Setting current location:', [
        'latitude' => $latitude,
        'longitude' => $longitude,
    ]);

    $this->currentLocation = [
        'latitude' => $latitude,
        'longitude' => $longitude,
    ];
    $this->isLoadingLocation = false;

    // 直接並べ替えを実行（緯度経度からの距離計算を使用）
    $this->sortField = 'distance';
    $this->sortDirection = 'asc';

    \Log::info('Current location set, sorting by distance using lat/lng calculation');
};

$calculateAndSortByDistance = function () {
    if (!$this->currentLocation['latitude'] || !$this->currentLocation['longitude']) {
        return;
    }

    $this->dispatch('calculate-distances', [
        'origin' => $this->currentLocation,
        'clients' => $this->clients->toArray(),
    ]);
};

$setClientDistances = function ($distances) {
    \Log::info('Setting client distances:', $distances);
    $this->clientDistances = $distances;
    $this->sortField = 'distance';
    $this->sortDirection = 'asc';

    // デバッグ用: 距離データの確認
    foreach ($distances as $clientId => $distanceData) {
        \Log::info("Client {$clientId} distance:", $distanceData);
    }
};

$getLastVisitDays = function ($client) {
    $lastVisit = $client->visits->first();
    if (!$lastVisit) {
        return null;
    }
    return (int) Carbon::parse($lastVisit->visited_at)->diffInDays(now());
};

$getLastVisitDate = function ($client) {
    $lastVisit = $client->visits->first();
    return $lastVisit ? $lastVisit->visited_at : null;
};

$calculateDistance = function ($lat1, $lon1, $lat2, $lon2) {
    if (!$lat1 || !$lon1 || !$lat2 || !$lon2) {
        return null;
    }

    // Haversine formula for calculating distance between two points
    $r = 6371; // 地球の半径（km）
    $lat1 = deg2rad($lat1);
    $lon1 = deg2rad($lon1);
    $lat2 = deg2rad($lat2);
    $lon2 = deg2rad($lon2);

    $dlon = $lon2 - $lon1;
    $dlat = $lat2 - $lat1;

    $a = sin($dlat / 2) ** 2 + cos($lat1) * cos($lat2) * sin($dlon / 2) ** 2;
    $c = 2 * asin(sqrt($a));

    $distance = $r * $c;

    \Log::info('Distance calculated:', [
        'from' => ['lat' => rad2deg($lat1), 'lng' => rad2deg($lon1)],
        'to' => ['lat' => rad2deg($lat2), 'lng' => rad2deg($lon2)],
        'distance_km' => $distance,
    ]);

    return $distance;
};

$updateLocation = function () {
    $this->dispatch('requestLocation');
};

$handleLocationUpdate = function ($latitude, $longitude) {
    $this->currentLocation = [
        'latitude' => $latitude,
        'longitude' => $longitude,
    ];
};

// 訪問先が追加された時の更新処理
on([
    'client-created' => function () {
        $this->clients = Client::with([
            'visits' => function ($query) {
                $query->latest('visited_at');
            },
        ])->get();
    },
]);

$filteredClients = computed(function () {
    $clients = $this->clients->filter(function ($client) {
        return str_contains(strtolower($client->name), strtolower($this->search));
    });

    // ソート処理
    return match ($this->sortField) {
        'last_visit_asc' => $clients->sortBy(function ($client) {
            return $this->getLastVisitDate($client) ?? '2000-01-01';
        }),
        'last_visit_desc' => $clients->sortByDesc(function ($client) {
            return $this->getLastVisitDate($client) ?? '2000-01-01';
        }),
        'distance' => $clients->sortBy(function ($client) {
            // 現在地からの距離を計算
            if ($this->currentLocation['latitude'] && $client->latitude) {
                $distance = $this->calculateDistance($this->currentLocation['latitude'], $this->currentLocation['longitude'], $client->latitude, $client->longitude);

                \Log::info('Sorting client by distance:', [
                    'client_id' => $client->id,
                    'client_name' => $client->name,
                    'distance_km' => $distance,
                ]);

                return $distance ?: PHP_FLOAT_MAX;
            }

            \Log::warning('Cannot calculate distance for client:', [
                'client_id' => $client->id,
                'client_name' => $client->name,
                'current_location' => $this->currentLocation,
                'client_latitude' => $client->latitude,
                'client_longitude' => $client->longitude,
            ]);

            return PHP_FLOAT_MAX;
        }),
        'name' => $clients->sortBy('name', SORT_REGULAR, $this->sortDirection === 'desc'),
        default => $clients,
    };
});

?>

<div x-data class="client-list-container" x-init="// 現在地取得のイベントリスナー
window.addEventListener('requestLocation', () => {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            position => {
                $wire.handleLocationUpdate(
                    position.coords.latitude,
                    position.coords.longitude
                );
            },
            error => {
                console.error('位置情報の取得に失敗しました:', error);
            }
        );
    }
});

// 現在地取得のイベントリスナー（新しい実装）
window.addEventListener('get-current-location', () => {
    console.log('Received get-current-location event');
    if (window.getCurrentLocation) {
        window.getCurrentLocation((location) => {
            if (location) {
                console.log('Successfully got location, setting in Livewire:', location);
                $wire.setCurrentLocation(location.lat, location.lng);
            } else {
                console.error('現在地の取得に失敗しました');
                $wire.set('isLoadingLocation', false);
            }
        });
    } else {
        console.error('getCurrentLocation function is not available');
        $wire.set('isLoadingLocation', false);
    }
});

// 距離計算のイベントリスナー
window.addEventListener('calculate-distances', (event) => {
    const { origin, clients } = event.detail;

    console.log('Calculating distances for clients:', clients.length);

    if (window.getDistances && clients.length > 0) {
        const destinations = clients.map(client => client.address).filter(address => address);

        console.log('Destinations to calculate:', destinations);

        if (destinations.length > 0) {
            window.getDistances(origin, destinations, (distances) => {
                console.log('Received distances:', distances);

                if (distances) {
                    const clientDistances = {};
                    clients.forEach((client, index) => {
                        if (client.address && distances[index]) {
                            clientDistances[client.id] = distances[index];
                        }
                    });

                    console.log('Setting client distances:', clientDistances);
                    $wire.setClientDistances(clientDistances);
                } else {
                    console.error('Failed to get distances from Google Maps API');
                    $wire.set('isLoadingLocation', false);
                }
            });
        } else {
            console.warn('No valid destinations found');
            $wire.set('isLoadingLocation', false);
        }
    } else {
        console.error('getDistances function not available or no clients');
        $wire.set('isLoadingLocation', false);
    }
});">
    <div class="mb-6 space-y-4 bg-white dark:bg-gray-800 rounded-lg shadow-sm p-4">
        <div class="relative">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <svg class="h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"
                    fill="currentColor">
                    <path fill-rule="evenodd"
                        d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z"
                        clip-rule="evenodd" />
                </svg>
            </div>
            <input type="text" wire:model.live="search" placeholder="会社名で検索..."
                class="block w-full pl-10 pr-3 py-3 border border-gray-300 dark:border-gray-600 rounded-lg leading-5 bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-150 ease-in-out sm:text-sm">
        </div>

        <div class="flex flex-wrap gap-2">
            <button wire:click="$set('sortField', 'last_visit_desc')"
                class="inline-flex items-center px-4 py-2 rounded-lg text-sm font-medium transition-all duration-200 ease-in-out transform hover:scale-105 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500
                    {{ $sortField === 'last_visit_desc'
                        ? 'bg-blue-600 text-white shadow-lg hover:bg-blue-700'
                        : 'bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-200 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-600' }}">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M3 4h13M3 8h9m-9 4h6m4 0l4-4m0 0l4 4m-4-4v12" />
                </svg>
                訪問日（新しい順）
            </button>

            <button wire:click="$set('sortField', 'last_visit_asc')"
                class="inline-flex items-center px-4 py-2 rounded-lg text-sm font-medium transition-all duration-200 ease-in-out transform hover:scale-105 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500
                    {{ $sortField === 'last_visit_asc'
                        ? 'bg-blue-600 text-white shadow-lg hover:bg-blue-700'
                        : 'bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-200 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-600' }}">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M3 4h13M3 8h9m-9 4h9m5-4v12m0 0l-4-4m4 4l4-4" />
                </svg>
                訪問日（古い順）
            </button>

            <button wire:click="sortByDistance" @disabled($isLoadingLocation)
                class="inline-flex items-center px-4 py-2 rounded-lg text-sm font-medium transition-all duration-200 ease-in-out transform hover:scale-105 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed
                    {{ $sortField === 'distance'
                        ? 'bg-blue-600 text-white shadow-lg hover:bg-blue-700'
                        : 'bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-200 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-600' }}">
                @if ($isLoadingLocation)
                    <svg class="animate-spin -ml-1 mr-2 h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none"
                        viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                            stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor"
                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                        </path>
                    </svg>
                    <span>位置情報取得中...</span>
                @else
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    <span>現在地から近い順</span>
                @endif
            </button>
        </div>
    </div>

    <!-- モバイル表示用のカードビュー -->
    <div class="block lg:hidden space-y-4">
        @foreach ($this->filteredClients as $client)
            <div
                class="bg-white dark:bg-gray-800 rounded-lg shadow-sm overflow-hidden hover:shadow-md transition-shadow duration-200">
                <div class="p-4">
                    <div class="flex justify-between items-start">
                        <div class="flex-1">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white">{{ $client->name }}</h3>
                            <a href="https://maps.google.com/maps?q={{ urlencode($client->address) }}" target="_blank"
                                class="mt-1 flex items-center text-sm text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300">
                                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                                {{ $client->address }}
                            </a>
                        </div>
                        @php
                            $days = $this->getLastVisitDays($client);
                        @endphp
                        <div @class([
                            'flex flex-col items-center justify-center px-4 py-2 rounded-lg text-center min-w-[80px] ml-2 shadow-sm border',
                            'bg-red-50 dark:bg-red-950/50 border-red-200 dark:border-red-800' =>
                                $days && $days > 30,
                            'bg-yellow-50 dark:bg-yellow-950/50 border-yellow-200 dark:border-yellow-800' =>
                                $days && $days > 14 && $days <= 30,
                            'bg-green-50 dark:bg-green-950/50 border-green-200 dark:border-green-800' =>
                                $days && $days <= 14,
                            'bg-gray-50 dark:bg-gray-800/50 border-gray-200 dark:border-gray-700' => !$days,
                        ])>
                            @if ($days)
                                <span @class([
                                    'text-2xl font-bold',
                                    'text-red-600 dark:text-red-300' => $days && $days > 30,
                                    'text-yellow-600 dark:text-yellow-300' =>
                                        $days && $days > 14 && $days <= 30,
                                    'text-green-600 dark:text-green-300' => $days && $days <= 14,
                                    'text-gray-600 dark:text-gray-300' => !$days,
                                ])>
                                    {{ $days }}
                                </span>
                                <span @class([
                                    'text-sm mt-1',
                                    'text-red-500 dark:text-red-400' => $days && $days > 30,
                                    'text-yellow-500 dark:text-yellow-400' =>
                                        $days && $days > 14 && $days <= 30,
                                    'text-green-500 dark:text-green-400' => $days && $days <= 14,
                                    'text-gray-500 dark:text-gray-400' => !$days,
                                ])>
                                    日前
                                </span>
                            @else
                                <span class="text-lg font-medium text-gray-600 dark:text-gray-300">
                                    未訪問
                                </span>
                            @endif
                        </div>
                    </div>

                    @if ($sortField === 'distance' && $this->currentLocation['latitude'] && $client->latitude)
                        <div class="mt-2 flex items-center text-sm text-gray-600 dark:text-gray-400">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M13 7l5 5m0 0l-5 5m5-5H6" />
                            </svg>
                            @php
                                $distance = $this->calculateDistance(
                                    $this->currentLocation['latitude'],
                                    $this->currentLocation['longitude'],
                                    $client->latitude,
                                    $client->longitude,
                                );
                            @endphp
                            @if ($distance)
                                約{{ round($distance, 1) }}km
                            @else
                                距離不明
                            @endif
                        </div>
                    @endif

                    <div class="mt-4 space-y-3 border-t border-gray-200 dark:border-gray-700 pt-4">
                        <div class="flex items-center text-sm text-gray-900 dark:text-white">
                            <svg class="w-4 h-4 mr-1.5 text-gray-500" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                            {{ $client->contact_person }}
                            @if ($client->department || $client->position)
                                <span class="ml-1 text-gray-500 dark:text-gray-400">
                                    ({{ $client->department }}{{ $client->position ? ' / ' . $client->position : '' }})
                                </span>
                            @endif
                        </div>

                        <div class="flex flex-col space-y-2">
                            @if ($client->phone)
                                <a href="tel:{{ $client->phone }}"
                                    class="inline-flex items-center text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white transition-colors duration-150">
                                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                    </svg>
                                    {{ $client->phone }}
                                </a>
                            @endif
                            @if ($client->email)
                                <a href="mailto:{{ $client->email }}"
                                    class="inline-flex items-center text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white transition-colors duration-150">
                                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                    </svg>
                                    {{ $client->email }}
                                </a>
                            @endif
                        </div>

                        @if ($client->notes)
                            <div class="text-sm text-gray-500 dark:text-gray-400">
                                {{ Str::limit($client->notes, 50) }}
                            </div>
                        @endif
                    </div>
                </div>

                <div class="px-4 py-3 bg-gray-50 dark:bg-gray-700/50 flex justify-end space-x-2">
                    <a href="{{ route('clients.detail', $client->id) }}"
                        class="inline-flex items-center px-3 py-1.5 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all duration-200">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                        詳細
                    </a>
                    <a href="{{ route('visits.create', $client->id) }}"
                        class="inline-flex items-center px-3 py-1.5 border border-transparent rounded-md text-sm font-medium text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition-all duration-200">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                        訪問記録
                    </a>
                </div>
            </div>
        @endforeach
    </div>

    <!-- デスクトップ表示用のテーブルビュー -->
    <div class="overflow-hidden bg-white dark:bg-gray-800 shadow-sm rounded-lg">
        <div class="overflow-x-auto">
            <div class="inline-block min-w-full align-middle">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th scope="col" wire:click="sortBy('name')"
                                class="group px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors duration-200">
                                <div class="flex items-center space-x-1">
                                    <span>会社名</span>
                                    <span class="text-gray-400 dark:text-gray-500">
                                        @if ($sortField === 'name')
                                            <svg class="w-4 h-4 transform {{ $sortDirection === 'asc' ? '' : 'rotate-180' }}"
                                                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M5 15l7-7 7 7" />
                                            </svg>
                                        @else
                                            <svg class="w-4 h-4 opacity-0 group-hover:opacity-100" fill="none"
                                                stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M5 15l7-7 7 7" />
                                            </svg>
                                        @endif
                                    </span>
                                </div>
                            </th>
                            <th scope="col"
                                class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                最終訪問
                            </th>
                            @if ($sortField === 'distance')
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    距離
                                </th>
                            @endif
                            <th scope="col"
                                class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                連絡先
                            </th>
                            <th scope="col"
                                class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                アクション
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-800">
                        @foreach ($this->filteredClients as $client)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors duration-150">
                                <td class="px-6 py-4">
                                    <div class="flex flex-col">
                                        <div class="text-sm font-medium text-gray-900 dark:text-white">
                                            {{ $client->name }}
                                        </div>
                                        <a href="https://maps.google.com/maps?q={{ urlencode($client->address) }}"
                                            target="_blank"
                                            class="text-sm text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 hover:underline flex items-center mt-1">
                                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                            </svg>
                                            {{ $client->address }}
                                        </a>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @php
                                        $days = $this->getLastVisitDays($client);
                                    @endphp
                                    <div @class([
                                        'flex flex-col items-center justify-center px-4 py-2 rounded-lg text-center min-w-[80px] shadow-sm border',
                                        'bg-red-50 dark:bg-red-950/50 border-red-200 dark:border-red-800' =>
                                            $days && $days > 30,
                                        'bg-yellow-50 dark:bg-yellow-950/50 border-yellow-200 dark:border-yellow-800' =>
                                            $days && $days > 14 && $days <= 30,
                                        'bg-green-50 dark:bg-green-950/50 border-green-200 dark:border-green-800' =>
                                            $days && $days <= 14,
                                        'bg-gray-50 dark:bg-gray-800/50 border-gray-200 dark:border-gray-700' => !$days,
                                    ])>
                                        @if ($days)
                                            <span @class([
                                                'text-2xl font-bold',
                                                'text-red-600 dark:text-red-300' => $days && $days > 30,
                                                'text-yellow-600 dark:text-yellow-300' =>
                                                    $days && $days > 14 && $days <= 30,
                                                'text-green-600 dark:text-green-300' => $days && $days <= 14,
                                                'text-gray-600 dark:text-gray-300' => !$days,
                                            ])>
                                                {{ $days }}
                                            </span>
                                            <span @class([
                                                'text-sm mt-1',
                                                'text-red-500 dark:text-red-400' => $days && $days > 30,
                                                'text-yellow-500 dark:text-yellow-400' =>
                                                    $days && $days > 14 && $days <= 30,
                                                'text-green-500 dark:text-green-400' => $days && $days <= 14,
                                                'text-gray-500 dark:text-gray-400' => !$days,
                                            ])>
                                                日前
                                            </span>
                                        @else
                                            <span class="text-lg font-medium text-gray-600 dark:text-gray-300">
                                                未訪問
                                            </span>
                                        @endif
                                    </div>
                                </td>
                                @if ($sortField === 'distance' && $this->currentLocation['latitude'] && $client->latitude)
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center text-sm text-gray-600 dark:text-gray-400">
                                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M13 7l5 5m0 0l-5 5m5-5H6" />
                                            </svg>
                                            @php
                                                $distance = $this->calculateDistance(
                                                    $this->currentLocation['latitude'],
                                                    $this->currentLocation['longitude'],
                                                    $client->latitude,
                                                    $client->longitude,
                                                );
                                            @endphp
                                            @if ($distance)
                                                約{{ round($distance, 1) }}km
                                            @else
                                                距離不明
                                            @endif
                                        </div>
                                    </td>
                                @endif
                                <td class="px-6 py-4">
                                    <div class="space-y-2">
                                        <div class="flex items-center text-sm text-gray-900 dark:text-white">
                                            <svg class="w-4 h-4 mr-1.5 text-gray-500" fill="none"
                                                stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                            </svg>
                                            {{ $client->contact_person }}
                                            @if ($client->department || $client->position)
                                                <span class="ml-1 text-gray-500 dark:text-gray-400">
                                                    ({{ $client->department }}{{ $client->position ? ' / ' . $client->position : '' }})
                                                </span>
                                            @endif
                                        </div>
                                        <div class="flex flex-col space-y-1">
                                            @if ($client->phone)
                                                <a href="tel:{{ $client->phone }}"
                                                    class="inline-flex items-center text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white transition-colors duration-150">
                                                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor"
                                                        viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2"
                                                            d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                                    </svg>
                                                    {{ $client->phone }}
                                                </a>
                                            @endif
                                            @if ($client->email)
                                                <a href="mailto:{{ $client->email }}"
                                                    class="inline-flex items-center text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white transition-colors duration-150">
                                                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor"
                                                        viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2"
                                                            d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                                    </svg>
                                                    {{ $client->email }}
                                                </a>
                                            @endif
                                        </div>
                                        @if ($client->notes)
                                            <div class="text-sm text-gray-500 dark:text-gray-400">
                                                {{ Str::limit($client->notes, 50) }}
                                            </div>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex justify-end space-x-2">
                                        <a href="{{ route('clients.detail', $client->id) }}"
                                            class="inline-flex items-center px-3 py-1.5 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all duration-200">
                                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                            詳細
                                        </a>
                                        <a href="{{ route('visits.create', $client->id) }}"
                                            class="inline-flex items-center px-3 py-1.5 border border-transparent rounded-md text-sm font-medium text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition-all duration-200">
                                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                            訪問記録
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
