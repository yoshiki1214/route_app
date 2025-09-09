<?php

use function Livewire\Volt\{state, computed};
use App\Models\Client;
use Carbon\Carbon;

state([
    'clients' => fn() => Client::with([
        'visits' => function ($query) {
            $query->latest('visited_at');
        },
    ])->get(),
    'search' => '',
    'sortField' => 'name',
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

<div x-data class="p-2 sm:p-4" x-init="window.addEventListener('requestLocation', () => {
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
        <input type="text" wire:model.live="search" placeholder="会社名で検索..."
            class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">

        <div class="flex flex-wrap gap-2">
            <button wire:click="sortBy('name')" @class([
                'px-3 py-1 text-sm rounded-full',
                'bg-blue-600 text-white' => $sortField === 'name',
                'bg-gray-200 text-gray-700 hover:bg-gray-300' => $sortField !== 'name',
            ])>
                会社名{{ $sortField === 'name' ? ($sortDirection === 'asc' ? '↑' : '↓') : '' }}
            </button>
            <button wire:click="$set('sortField', 'last_visit_desc')" @class([
                'px-3 py-1 text-sm rounded-full',
                'bg-blue-600 text-white' => $sortField === 'last_visit_desc',
                'bg-gray-200 text-gray-700 hover:bg-gray-300' =>
                    $sortField !== 'last_visit_desc',
            ])>
                訪問日（新しい順）
            </button>
            <button wire:click="$set('sortField', 'last_visit_asc')" @class([
                'px-3 py-1 text-sm rounded-full',
                'bg-blue-600 text-white' => $sortField === 'last_visit_asc',
                'bg-gray-200 text-gray-700 hover:bg-gray-300' =>
                    $sortField !== 'last_visit_asc',
            ])>
                訪問日（古い順）
            </button>
            <button wire:click="updateLocation" @class([
                'px-3 py-1 text-sm rounded-full',
                'bg-blue-600 text-white' => $sortField === 'distance',
                'bg-gray-200 text-gray-700 hover:bg-gray-300' => $sortField !== 'distance',
            ])>
                現在地から近い順
            </button>
        </div>
    </div>

    <!-- モバイル表示用のカードビュー -->
    <div class="block lg:hidden space-y-4">
        @foreach ($this->filteredClients as $client)
            <div class="bg-white rounded-lg shadow p-4 space-y-3">
                <div class="flex justify-between items-start">
                    <div>
                        <div class="text-lg font-medium text-gray-900">{{ $client->name }}</div>
                        <div class="text-sm text-gray-500">{{ $client->address }}</div>
                        @if ($sortField === 'distance' && $this->currentLocation['latitude'])
                            <div class="text-sm text-gray-500">
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
                        'text-sm px-2 py-1 rounded-full',
                        'bg-red-100 text-red-800' => $days && $days > 30,
                        'bg-yellow-100 text-yellow-800' => $days && $days > 14 && $days <= 30,
                        'bg-green-100 text-green-800' => $days && $days <= 14,
                        'bg-gray-100 text-gray-800' => !$days,
                    ])>
                        @if ($days)
                            {{ $days }}日前
                        @else
                            未訪問
                        @endif
                    </div>
                </div>

                <div class="border-t pt-3">
                    <div class="text-sm">
                        <div class="font-medium">
                            {{ $client->contact_person }}
                            @if ($client->department || $client->position)
                                <span class="text-gray-500">
                                    ({{ $client->department }}{{ $client->position ? ' / ' . $client->position : '' }})
                                </span>
                            @endif
                        </div>
                        <div class="space-y-1 mt-1 text-gray-500">
                            @if ($client->phone)
                                <a href="tel:{{ $client->phone }}" class="block">
                                    📱 {{ $client->phone }}
                                </a>
                            @endif
                            @if ($client->fax)
                                <div>📠 {{ $client->fax }}</div>
                            @endif
                            @if ($client->email)
                                <a href="mailto:{{ $client->email }}" class="block">
                                    ✉️ {{ $client->email }}
                                </a>
                            @endif
                        </div>
                    </div>
                    @if ($client->notes)
                        <div class="mt-2 text-sm text-gray-500 italic">
                            {{ Str::limit($client->notes, 50) }}
                        </div>
                    @endif
                </div>

                <div class="flex justify-end space-x-3 pt-2 border-t">
                    <a href="#" class="text-blue-600 hover:text-blue-900">詳細</a>
                    <a href="#" class="text-green-600 hover:text-green-900">訪問記録</a>
                </div>
            </div>
        @endforeach
    </div>

    <!-- デスクトップ表示用のテーブルビュー -->
    <div class="hidden lg:block">
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th wire:click="sortBy('name')"
                                class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer">
                                会社名
                                @if ($sortField === 'name')
                                    <span class="ml-1">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                                @endif
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                最終訪問
                            </th>
                            @if ($sortField === 'distance' && $this->currentLocation['latitude'])
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    距離
                                </th>
                            @endif
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                連絡先
                            </th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                アクション
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach ($this->filteredClients as $client)
                            <tr>
                                <td class="px-6 py-4">
                                    <div class="text-sm font-medium text-gray-900">{{ $client->name }}</div>
                                    <div class="text-sm text-gray-500">{{ $client->address }}</div>
                                </td>
                                <td class="px-6 py-4">
                                    @php
                                        $days = $this->getLastVisitDays($client);
                                    @endphp
                                    <div @class([
                                        'text-sm px-2 py-1 rounded-full inline-block',
                                        'bg-red-100 text-red-800' => $days && $days > 30,
                                        'bg-yellow-100 text-yellow-800' => $days && $days > 14 && $days <= 30,
                                        'bg-green-100 text-green-800' => $days && $days <= 14,
                                        'bg-gray-100 text-gray-800' => !$days,
                                    ])>
                                        @if ($days)
                                            {{ $days }}日前
                                        @else
                                            未訪問
                                        @endif
                                    </div>
                                </td>
                                @if ($sortField === 'distance' && $this->currentLocation['latitude'])
                                    <td class="px-6 py-4">
                                        <div class="text-sm text-gray-900">
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
                                <td class="px-6 py-4">
                                    <div class="text-sm text-gray-900">
                                        {{ $client->contact_person }}
                                        @if ($client->department || $client->position)
                                            <span class="text-gray-500">
                                                ({{ $client->department }}{{ $client->position ? ' / ' . $client->position : '' }})
                                            </span>
                                        @endif
                                    </div>
                                    <div class="text-sm text-gray-500 space-y-1">
                                        @if ($client->phone)
                                            <a href="tel:{{ $client->phone }}" class="block hover:text-blue-600">
                                                📱 {{ $client->phone }}
                                            </a>
                                        @endif
                                        @if ($client->fax)
                                            <div>📠 {{ $client->fax }}</div>
                                        @endif
                                        @if ($client->email)
                                            <a href="mailto:{{ $client->email }}" class="block hover:text-blue-600">
                                                ✉️ {{ $client->email }}
                                            </a>
                                        @endif
                                    </div>
                                    @if ($client->notes)
                                        <div class="mt-2 text-sm text-gray-500 italic">
                                            {{ Str::limit($client->notes, 50) }}
                                        </div>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-right text-sm font-medium">
                                    <a href="#" class="text-blue-600 hover:text-blue-900">詳細</a>
                                    <a href="#" class="ml-4 text-green-600 hover:text-green-900">訪問記録</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
