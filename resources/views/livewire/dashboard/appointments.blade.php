<?php

use function Livewire\Volt\{state};
use App\Models\Appointment;
use App\Services\RouteOptimizer;
use Carbon\Carbon;

state([
    'appointments' => [],
    'travelTimes' => [],
    'mapApiKey' => '',
]);

$mount = function () {
    $this->mapApiKey = config('services.google.maps_api_key');
    $this->loadAppointments();
};

$loadAppointments = function () {
    // 今日のアポイントメントを開始時間順に取得
    $this->appointments = Appointment::with('client')->whereDate('start_datetime', Carbon::today())->orderBy('start_datetime')->get();

    // アポイントメント間の移動時間を計算
    if (count($this->appointments) > 1) {
        $this->calculateTravelTimes();
    }
};

$calculateTravelTimes = function () {
    $optimizer = new RouteOptimizer();
    $this->travelTimes = [];

    // デバッグ用に計算開始をログに出力
    \Log::info('Calculating travel times...');

    // アポイントメントを時間順にソート
    $sortedAppointments = $this->appointments->sortBy('start_datetime')->values();

    \Log::info(
        'Sorted appointments:',
        $sortedAppointments
            ->map(function ($appointment) {
                return [
                    'id' => $appointment->id,
                    'client' => $appointment->client->name,
                    'start_time' => $appointment->start_datetime,
                ];
            })
            ->all(),
    );

    // アポイントメント間の移動時間を計算
    for ($i = 0; $i < $sortedAppointments->count() - 1; $i++) {
        $current = $sortedAppointments[$i];
        $next = $sortedAppointments[$i + 1];

        // デバッグ用に現在の計算をログに出力
        \Log::info("Calculating route from {$current->client->name} to {$next->client->name}", [
            'from_lat' => $current->client->latitude,
            'from_lng' => $current->client->longitude,
            'to_lat' => $next->client->latitude,
            'to_lng' => $next->client->longitude,
        ]);

        // Google Maps APIを使用して移動時間を計算
        $result = $optimizer->getDirectionsData(
            [
                'lat' => $current->client->latitude,
                'lng' => $current->client->longitude,
            ],
            [
                'lat' => $next->client->latitude,
                'lng' => $next->client->longitude,
            ],
            auth()->user()->travel_mode,
            auth()->user()->use_toll_roads,
            auth()->user()->use_highways,
        );

        if ($result) {
            $this->travelTimes[$current->id] = [
                'duration' => $result['duration'],
                'duration_text' => $result['duration_text'],
                'distance' => $result['distance'],
                'distance_text' => $result['distance_text'],
                'next_appointment_id' => $next->id,
            ];
        } else {
            \Log::warning('Failed to calculate travel time', [
                'from' => $current->client->name,
                'to' => $next->client->name,
            ]);
        }
    }
};

$generateGoogleMapsUrl = function ($currentAppointmentId) {
    // デバッグ用にURLの生成開始をログに出力
    \Log::info("Generating Google Maps URL for appointment: {$currentAppointmentId}");

    // 現在のアポイントメントを取得
    $currentAppointment = $this->appointments->firstWhere('id', $currentAppointmentId);
    if (!$currentAppointment) {
        \Log::warning("Current appointment not found: {$currentAppointmentId}");
        return '';
    }

    // 次のアポイントメントIDを取得
    $nextAppointmentId = $this->travelTimes[$currentAppointmentId]['next_appointment_id'] ?? null;
    if (!$nextAppointmentId) {
        \Log::warning("Next appointment ID not found for: {$currentAppointmentId}");
        return '';
    }

    // 次のアポイントメントを取得
    $nextAppointment = $this->appointments->firstWhere('id', $nextAppointmentId);

    // デバッグ用に取得したアポイントメントの情報をログに出力
    \Log::info('Found appointments:', [
        'current' => $currentAppointment
            ? [
                'id' => $currentAppointment->id,
                'client' => $currentAppointment->client->name,
                'lat' => $currentAppointment->client->latitude,
                'lng' => $currentAppointment->client->longitude,
            ]
            : null,
        'next' => $nextAppointment
            ? [
                'id' => $nextAppointment->id,
                'client' => $nextAppointment->client->name,
                'lat' => $nextAppointment->client->latitude,
                'lng' => $nextAppointment->client->longitude,
            ]
            : null,
    ]);

    if (!$currentAppointment || !$nextAppointment) {
        return '';
    }

    $user = auth()->user();
    $params = [
        'api' => 1,
        'origin' => $currentAppointment->client->latitude . ',' . $currentAppointment->client->longitude,
        'destination' => $nextAppointment->client->latitude . ',' . $nextAppointment->client->longitude,
        'travelmode' => $user->travel_mode,
    ];

    // 車の場合、回避オプションを追加
    if ($user->travel_mode === 'driving') {
        $avoid = [];
        if (!$user->use_toll_roads) {
            $avoid[] = 'tolls';
        }
        if (!$user->use_highways) {
            $avoid[] = 'highways';
        }
        if (!empty($avoid)) {
            $params['avoid'] = implode('|', $avoid);
        }
    }

    return 'https://www.google.com/maps/dir/?' . http_build_query($params);
};

?>

<div class="space-y-4">
    <h2 class="text-lg font-semibold">本日のアポイントメント</h2>

    @if ($appointments->isEmpty())
        <p class="text-gray-600 dark:text-gray-400">本日のアポイントメントはありません。</p>
    @else
        <div class="space-y-4">
            @foreach ($appointments as $index => $appointment)
                <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-4">
                    <div class="flex items-start justify-between">
                        <div>
                            <h3 class="font-medium text-gray-900 dark:text-gray-100">
                                {{ $appointment->title }}
                            </h3>
                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                {{ $appointment->client->name }}
                            </p>
                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                {{ Carbon::parse($appointment->start_datetime)->format('H:i') }} 〜
                                {{ Carbon::parse($appointment->end_datetime)->format('H:i') }}
                            </p>
                        </div>

                        @if (isset($travelTimes[$appointment->id]))
                            <div class="text-right flex flex-col items-end">
                                <div class="flex items-center gap-2 mb-1">
                                    <div class="text-sm text-gray-600 dark:text-gray-400">
                                        移動: {{ $travelTimes[$appointment->id]['duration_text'] }}
                                        ({{ $travelTimes[$appointment->id]['distance_text'] }})
                                    </div>
                                    <a href="{{ $this->generateGoogleMapsUrl($appointment->id) }}" target="_blank"
                                        class="inline-flex items-center text-sm text-blue-600 hover:text-blue-500 dark:text-blue-400 dark:hover:text-blue-300">
                                        <span>ルートを表示</span>
                                        <svg class="ml-1 h-4 w-4" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                        </svg>
                                    </a>
                                </div>
                            </div>
                        @endif
                    </div>

                    @if ($appointment->memo)
                        <div class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                            {{ $appointment->memo }}
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</div>
