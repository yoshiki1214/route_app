<?php

use function Livewire\Volt\{state, js};
use App\Models\Visit;

state([
    'latitude' => null,
    'longitude' => null,
    'accuracy' => null,
    'error' => null,
    'tracking' => false,
]);

$startTracking = function () {
    $this->tracking = true;
};

$stopTracking = function () {
    $this->tracking = false;
};

$updateLocation = function ($lat, $lng, $acc) {
    $this->latitude = $lat;
    $this->longitude = $lng;
    $this->accuracy = $acc;
    $this->error = null;
};

$handleError = function ($message) {
    $this->error = $message;
};

$recordVisit = function ($clientId) {
    if (!$this->latitude || !$this->longitude) {
        $this->error = '位置情報が取得できていません。';
        return;
    }

    Visit::create([
        'client_id' => $clientId,
        'user_id' => auth()->id(),
        'visited_at' => now(),
        'latitude' => $this->latitude,
        'longitude' => $this->longitude,
        'visit_type' => 'in_person',
        'status' => 'completed',
    ]);

    $this->dispatch('visit-recorded');
};

?>

<div x-data="{
    watchId: null,
    startWatching() {
        if (!navigator.geolocation) {
            @this.handleError('お使いのブラウザは位置情報をサポートしていません。');
            return;
        }

        this.watchId = navigator.geolocation.watchPosition(
            position => {
                @this.updateLocation(
                    position.coords.latitude,
                    position.coords.longitude,
                    position.coords.accuracy
                );
            },
            error => {
                let message = '位置情報の取得に失敗しました。';
                switch (error.code) {
                    case error.PERMISSION_DENIED:
                        message = '位置情報の使用が許可されていません。';
                        break;
                    case error.POSITION_UNAVAILABLE:
                        message = '位置情報を取得できませんでした。';
                        break;
                    case error.TIMEOUT:
                        message = '位置情報の取得がタイムアウトしました。';
                        break;
                }
                @this.handleError(message);
            }, {
                enableHighAccuracy: true,
                timeout: 5000,
                maximumAge: 0
            }
        );
        @this.startTracking();
    },
    stopWatching() {
        if (this.watchId !== null) {
            navigator.geolocation.clearWatch(this.watchId);
            this.watchId = null;
            @this.stopTracking();
        }
    }
}" x-init="$watch('$wire.tracking', value => value ? startWatching() : stopWatching())">

    <div class="p-4 bg-white rounded-lg shadow">
        <h3 class="text-lg font-semibold mb-4">位置情報トラッカー</h3>

        @if ($error)
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                {{ $error }}
            </div>
        @endif

        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <span class="text-gray-700">ステータス:</span>
                <span class="font-medium {{ $tracking ? 'text-green-600' : 'text-gray-600' }}">
                    {{ $tracking ? '追跡中' : '停止中' }}
                </span>
            </div>

            @if ($latitude && $longitude)
                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-gray-700">緯度:</span>
                        <span class="font-mono">{{ number_format($latitude, 6) }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-gray-700">経度:</span>
                        <span class="font-mono">{{ number_format($longitude, 6) }}</span>
                    </div>
                    @if ($accuracy)
                        <div class="flex items-center justify-between">
                            <span class="text-gray-700">精度:</span>
                            <span>{{ number_format($accuracy) }}m</span>
                        </div>
                    @endif
                </div>
            @endif

            <div class="flex justify-end space-x-2">
                @if (!$tracking)
                    <button type="button" wire:click="startTracking"
                        class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                        追跡開始
                    </button>
                @else
                    <button type="button" wire:click="stopTracking"
                        class="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700">
                        追跡停止
                    </button>
                @endif
            </div>
        </div>
    </div>
</div>
