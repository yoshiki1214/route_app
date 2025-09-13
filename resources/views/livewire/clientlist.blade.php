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
    <div class="mb-4 space-y-2">
        <input type="text" wire:model.live="search" placeholder="会社名で検索..." class="client-search-input">

        <div class="flex flex-wrap gap-2">
            <button wire:click="$set('sortField', 'last_visit_desc')" @class([
                'client-sort-button',
                'client-sort-button-active' => $sortField === 'last_visit_desc',
                'client-sort-button-inactive' => $sortField !== 'last_visit_desc',
            ])>
                訪問日（新しい順）
            </button>
            <button wire:click="$set('sortField', 'last_visit_asc')" @class([
                'client-sort-button',
                'client-sort-button-active' => $sortField === 'last_visit_asc',
                'client-sort-button-inactive' => $sortField !== 'last_visit_asc',
            ])>
                訪問日（古い順）
            </button>
            <button wire:click="sortByDistance" @class([
                'client-sort-button',
                'client-sort-button-active' => $sortField === 'distance',
                'client-sort-button-inactive' => $sortField !== 'distance',
            ]) @disabled($isLoadingLocation)>
                @if ($isLoadingLocation)
                    <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white inline" xmlns="http://www.w3.org/2000/svg"
                        fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                            stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor"
                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                        </path>
                    </svg>
                    位置情報取得中...
                @else
                    現在地から近い順
                @endif
            </button>
        </div>
    </div>

    <!-- モバイル表示用のカードビュー -->
    <div class="block lg:hidden space-y-4">
        @foreach ($this->filteredClients as $client)
            <div class="client-card">
                <div class="client-card-header">
                    <div>
                        <div class="client-card-info">{{ $client->name }}</div>
                        <a href="https://maps.google.com/maps?q={{ urlencode($client->address) }}" target="_blank"
                            class="client-card-address text-blue-600 hover:text-blue-800 underline">
                            {{ $client->address }}
                        </a>
                        @if ($sortField === 'distance' && $this->currentLocation['latitude'] && $client->latitude)
                            <div class="client-card-distance">
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
                    </div>
                    @php
                        $days = $this->getLastVisitDays($client);
                    @endphp
                    <div @class([
                        'client-visit-badge',
                        'client-visit-badge-red' => $days && $days > 30,
                        'client-visit-badge-yellow' => $days && $days > 14 && $days <= 30,
                        'client-visit-badge-green' => $days && $days <= 14,
                        'client-visit-badge-gray' => !$days,
                    ])>
                        @if ($days)
                            {{ $days }}日前
                        @else
                            未訪問
                        @endif
                    </div>
                </div>

                <div class="client-card-divider">
                    <div class="client-contact-info">
                        <div class="client-contact-person">
                            {{ $client->contact_person }}
                            @if ($client->department || $client->position)
                                <span class="text-gray-500">
                                    ({{ $client->department }}{{ $client->position ? ' / ' . $client->position : '' }})
                                </span>
                            @endif
                        </div>
                        <div class="client-contact-details">
                            @if ($client->phone)
                                <a href="tel:{{ $client->phone }}" class="client-contact-link">
                                    📱 {{ $client->phone }}
                                </a>
                            @endif
                            @if ($client->email)
                                <a href="mailto:{{ $client->email }}" class="client-contact-link">
                                    ✉️ {{ $client->email }}
                                </a>
                            @endif
                        </div>
                    </div>
                    @if ($client->notes)
                        <div class="client-notes">
                            {{ Str::limit($client->notes, 50) }}
                        </div>
                    @endif
                </div>

                <div class="client-card-actions">
                    <a href="{{ route('clients.detail', $client->id) }}" class="client-action-link">詳細</a>
                    <a href="{{ route('visits.create', $client->id) }}" class="client-action-link-green">訪問記録</a>
                </div>
            </div>
        @endforeach
    </div>

    <!-- デスクトップ表示用のテーブルビュー -->
    <div class="client-table-container">
        <div class="client-table">
            <div class="client-table-scroll">
                <table class="client-table-main">
                    <thead class="client-table-header">
                        <tr>
                            <th wire:click="sortBy('name')"
                                class="client-table-header-cell client-table-header-cell-sortable">
                                会社名
                                @if ($sortField === 'name')
                                    <span class="ml-1">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                @endif
                            </th>
                            <th class="client-table-header-cell">
                                最終訪問
                            </th>
                            @if ($sortField === 'distance')
                                <th class="client-table-header-cell">
                                    距離
                                </th>
                            @endif
                            <th class="client-table-header-cell">
                                連絡先
                            </th>
                            <th class="client-table-header-cell client-table-cell-right">
                                アクション
                            </th>
                        </tr>
                    </thead>
                    <tbody class="client-table-body">
                        @foreach ($this->filteredClients as $client)
                            <tr>
                                <td class="client-table-cell">
                                    <div class="client-table-company-name">{{ $client->name }}</div>
                                    <a href="https://maps.google.com/maps?q={{ urlencode($client->address) }}"
                                        target="_blank"
                                        class="client-table-company-address text-blue-600 hover:text-blue-800 underline">
                                        {{ $client->address }}
                                    </a>
                                </td>
                                <td class="client-table-cell client-table-cell-nowrap">
                                    @php
                                        $days = $this->getLastVisitDays($client);
                                    @endphp
                                    <div @class([
                                        'client-visit-badge',
                                        'client-visit-badge-red' => $days && $days > 30,
                                        'client-visit-badge-yellow' => $days && $days > 14 && $days <= 30,
                                        'client-visit-badge-green' => $days && $days <= 14,
                                        'client-visit-badge-gray' => !$days,
                                    ])>
                                        @if ($days)
                                            {{ $days }}日前
                                        @else
                                            未訪問
                                        @endif
                                    </div>
                                </td>
                                @if ($sortField === 'distance' && $this->currentLocation['latitude'] && $client->latitude)
                                    <td class="client-table-cell">
                                        <div class="client-table-distance">
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
                                <td class="client-table-cell">
                                    <div class="client-table-contact-person">
                                        {{ $client->contact_person }}
                                        @if ($client->department || $client->position)
                                            <span class="text-gray-500">
                                                ({{ $client->department }}{{ $client->position ? ' / ' . $client->position : '' }})
                                            </span>
                                        @endif
                                    </div>
                                    <div class="client-table-contact-details">
                                        @if ($client->phone)
                                            <a href="tel:{{ $client->phone }}" class="client-table-contact-link">
                                                📱 {{ $client->phone }}
                                            </a>
                                        @endif
                                        @if ($client->email)
                                            <a href="mailto:{{ $client->email }}" class="client-table-contact-link">
                                                ✉️ {{ $client->email }}
                                            </a>
                                        @endif
                                    </div>
                                    @if ($client->notes)
                                        <div class="client-notes">
                                            {{ Str::limit($client->notes, 50) }}
                                        </div>
                                    @endif
                                </td>
                                <td class="client-table-cell client-table-cell-right">
                                    <a href="{{ route('clients.detail', $client->id) }}"
                                        class="client-action-link">詳細</a>
                                    <a href="{{ route('visits.create', $client->id) }}"
                                        class="client-action-link-green">訪問記録</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
