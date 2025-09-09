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
]);

$sortBy = function ($field) {
    if ($this->sortField === $field) {
        $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
    } else {
        $this->sortField = $field;
        $this->sortDirection = 'asc';
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

    $r = 6371; // 地球の半径（km）
    $lat1 = deg2rad($lat1);
    $lon1 = deg2rad($lon1);
    $lat2 = deg2rad($lat2);
    $lon2 = deg2rad($lon2);

    $dlon = $lon2 - $lon1;
    $dlat = $lat2 - $lat1;

    $a = sin($dlat / 2) ** 2 + cos($lat1) * cos($lat2) * sin($dlon / 2) ** 2;
    $c = 2 * asin(sqrt($a));

    return $r * $c;
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
            if (!$this->currentLocation['latitude']) {
                return PHP_FLOAT_MAX;
            }
            return $this->calculateDistance($this->currentLocation['latitude'], $this->currentLocation['longitude'], $client->latitude, $client->longitude);
        }),
        'name' => $clients->sortBy('name', SORT_REGULAR, $this->sortDirection === 'desc'),
        default => $clients,
    };
});

?>

<div x-data class="client-list-container" x-init="window.addEventListener('requestLocation', () => {
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
            <button wire:click="updateLocation" @class([
                'client-sort-button',
                'client-sort-button-active' => $sortField === 'distance',
                'client-sort-button-inactive' => $sortField !== 'distance',
            ])>
                現在地から近い順
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
                        <div class="client-card-address">{{ $client->address }}</div>
                        @if ($sortField === 'distance' && $this->currentLocation['latitude'])
                            <div class="client-card-distance">
                                約{{ round(
                                    $this->calculateDistance(
                                        $this->currentLocation['latitude'],
                                        $this->currentLocation['longitude'],
                                        $client->latitude,
                                        $client->longitude,
                                    ),
                                ) }}km
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
                            @if ($sortField === 'distance' && $this->currentLocation['latitude'])
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
                                    <div class="client-table-company-address">{{ $client->address }}</div>
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
                                @if ($sortField === 'distance' && $this->currentLocation['latitude'])
                                    <td class="client-table-cell">
                                        <div class="client-table-distance">
                                            約{{ round(
                                                $this->calculateDistance(
                                                    $this->currentLocation['latitude'],
                                                    $this->currentLocation['longitude'],
                                                    $client->latitude,
                                                    $client->longitude,
                                                ),
                                            ) }}km
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
