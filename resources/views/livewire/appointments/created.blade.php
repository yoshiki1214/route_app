<?php

use function Livewire\Volt\{state, mount, computed};
use App\Models\Appointment;
use App\Models\Client;
use Carbon\Carbon;

state([
    'appointment' => null,
    'appointmentDate' => null,
    'nearbyClients' => [],
]);

mount(function ($appointmentId = null) {
    \Log::info('Created page mount:', [
        'appointment_id' => $appointmentId,
        'appointment_id_type' => gettype($appointmentId),
    ]);

    if ($appointmentId) {
        $this->appointment = Appointment::with('client')->find($appointmentId);
        if ($this->appointment) {
            \Log::info('Appointment found:', [
                'appointment_id' => $this->appointment->id,
                'client_name' => $this->appointment->client->name,
            ]);
            $this->appointmentDate = Carbon::parse($this->appointment->start_datetime)->format('Y-m-d');
            $this->loadNearbyClients();
        } else {
            \Log::error('Appointment not found:', [
                'appointment_id' => $appointmentId,
            ]);
        }
    } else {
        \Log::error('Appointment ID not provided to created page');
    }
});

$loadNearbyClients = function () {
    if (!$this->appointment || !$this->appointment->client) {
        return;
    }

    $targetClient = $this->appointment->client;

    // 同じ日にアポイントメントがないクライアントを取得
    $clientsWithAppointments = Appointment::whereDate('start_datetime', $this->appointmentDate)->pluck('client_id')->toArray();

    // 対象クライアント以外で、同じ日にアポイントメントがないクライアントを取得
    $availableClients = Client::where('id', '!=', $targetClient->id)->whereNotIn('id', $clientsWithAppointments)->whereNotNull('latitude')->whereNotNull('longitude')->get();

    // 距離でソートして上位3件を取得
    $this->nearbyClients = $availableClients
        ->map(function ($client) use ($targetClient) {
            $distance = $this->calculateDistance($targetClient->latitude, $targetClient->longitude, $client->latitude, $client->longitude);

            return [
                'client' => $client,
                'distance' => $distance,
            ];
        })
        ->sortBy('distance')
        ->take(3)
        ->values();
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

$todaysAppointments = computed(function () {
    if (!$this->appointmentDate) {
        return collect();
    }

    return Appointment::with('client')->whereDate('start_datetime', $this->appointmentDate)->orderBy('start_datetime')->get();
});

?>

<div class="min-h-screen bg-gray-100">
    <div class="py-6">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            @if ($this->appointment)
                <!-- 完了メッセージ -->
                <div class="bg-white rounded-lg shadow p-8 mb-6 text-center">
                    <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-green-100 mb-4">
                        <svg class="h-6 w-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7">
                            </path>
                        </svg>
                    </div>
                    <h1 class="text-2xl font-semibold text-gray-900 mb-2">アポイントメントが正常に作成されました</h1>
                    <p class="text-gray-600 mb-6">
                        {{ $this->appointment->client->name }} とのアポイントメントを登録しました
                    </p>

                    <!-- アポイントメント詳細 -->
                    <div class="bg-gray-50 rounded-lg p-4 mb-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-3">作成されたアポイントメント</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-left">
                            <div>
                                <span class="text-sm text-gray-500">件名:</span>
                                <span
                                    class="text-sm font-medium text-gray-900 ml-2">{{ $this->appointment->title }}</span>
                            </div>
                            <div>
                                <span class="text-sm text-gray-500">訪問種別:</span>
                                <span
                                    class="text-sm font-medium text-gray-900 ml-2">{{ $this->appointment->visit_type }}</span>
                            </div>
                            <div>
                                <span class="text-sm text-gray-500">開始日時:</span>
                                <span class="text-sm font-medium text-gray-900 ml-2">
                                    {{ Carbon::parse($this->appointment->start_datetime)->format('Y年m月d日 H:i') }}
                                </span>
                            </div>
                            <div>
                                <span class="text-sm text-gray-500">終了日時:</span>
                                <span class="text-sm font-medium text-gray-900 ml-2">
                                    {{ Carbon::parse($this->appointment->end_datetime)->format('Y年m月d日 H:i') }}
                                </span>
                            </div>
                        </div>
                        @if ($this->appointment->memo)
                            <div class="mt-4">
                                <span class="text-sm text-gray-500">メモ:</span>
                                <p class="text-sm text-gray-900 mt-1">{{ $this->appointment->memo }}</p>
                            </div>
                        @endif
                    </div>

                    <!-- アクションボタン -->
                    <div class="flex flex-col sm:flex-row gap-3 justify-center">
                        <a href="{{ route('appointments.create', ['client_id' => $this->appointment->client->id]) }}"
                            class="client-button-primary">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 4v16m8-8H4"></path>
                            </svg>
                            同じクライアントに追加
                        </a>
                        <a href="{{ route('appointments.index') }}" class="client-button-secondary">
                            アポイントメント一覧
                        </a>
                        <a href="{{ route('dashboard') }}" class="client-button-secondary">
                            ダッシュボード
                        </a>
                    </div>
                </div>

                <!-- 当日の予定 -->
                <div class="bg-white rounded-lg shadow p-6 mb-6">
                    <h2 class="text-lg font-medium text-gray-900 mb-4">
                        {{ Carbon::parse($this->appointmentDate)->format('Y年m月d日') }} の予定
                    </h2>
                    <div class="space-y-3">
                        @forelse ($this->todaysAppointments as $appointment)
                            <div class="flex items-center justify-between p-3 border border-gray-200 rounded-lg">
                                <div class="flex-1">
                                    <div class="flex items-center space-x-3">
                                        <div class="flex-shrink-0">
                                            <div class="h-2 w-2 bg-blue-500 rounded-full"></div>
                                        </div>
                                        <div>
                                            <h3 class="text-sm font-medium text-gray-900">{{ $appointment->title }}
                                            </h3>
                                            <p class="text-sm text-gray-500">{{ $appointment->client->name }}</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <p class="text-sm font-medium text-gray-900">
                                        {{ Carbon::parse($appointment->start_datetime)->format('H:i') }} -
                                        {{ Carbon::parse($appointment->end_datetime)->format('H:i') }}
                                    </p>
                                    <p class="text-xs text-gray-500">{{ $appointment->visit_type }}</p>
                                </div>
                            </div>
                        @empty
                            <p class="text-gray-500 text-center py-4">この日の予定はありません</p>
                        @endforelse
                    </div>
                </div>

                <!-- 近い会社の提案 -->
                @if ($this->nearbyClients->isNotEmpty())
                    <div class="bg-white rounded-lg shadow p-6">
                        <h2 class="text-lg font-medium text-gray-900 mb-4">
                            {{ $this->appointment->client->name }} に近い会社（同じ日にアポイントメントなし）
                        </h2>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            @foreach ($this->nearbyClients as $nearbyClient)
                                <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow">
                                    <h3 class="text-sm font-medium text-gray-900 mb-2">
                                        {{ $nearbyClient['client']->name }}</h3>
                                    <p class="text-xs text-gray-500 mb-2">{{ $nearbyClient['client']->address }}</p>
                                    <p class="text-xs text-blue-600 mb-3">約{{ round($nearbyClient['distance'], 1) }}km
                                    </p>
                                    <a href="{{ route('appointments.create', ['client_id' => $nearbyClient['client']->id]) }}"
                                        class="text-xs text-blue-600 hover:text-blue-800 underline">
                                        アポイントメントを追加
                                    </a>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            @else
                <!-- エラー表示 -->
                <div class="bg-white rounded-lg shadow p-8 text-center">
                    <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100 mb-4">
                        <svg class="h-6 w-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </div>
                    <h1 class="text-2xl font-semibold text-gray-900 mb-2">アポイントメントが見つかりません</h1>
                    <p class="text-gray-600 mb-6">指定されたアポイントメントは存在しないか、削除されています。</p>
                    <a href="{{ route('appointments.index') }}" class="client-button-primary">
                        アポイントメント一覧に戻る
                    </a>
                </div>
            @endif
        </div>
    </div>
</div>
