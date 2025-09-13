<?php

use function Livewire\Volt\{state, computed};
use App\Models\Client;
use App\Models\Appointment;
use Carbon\Carbon;

state([
    'activeTab' => 'today',
    'selectedClient' => null,
]);

$selectClient = function ($clientId) {
    $this->selectedClient = Client::find($clientId);
};

$refreshClients = function () {
    $this->dispatch('refresh-clients');
};

$todayAppointments = computed(function () {
    $today = Carbon::today();
    return Appointment::with(['client', 'user'])
        ->whereDate('start_datetime', $today)
        ->orderBy('start_datetime')
        ->get();
});

$upcomingAppointments = computed(function () {
    $today = Carbon::today();
    return Appointment::with(['client', 'user'])
        ->whereDate('start_datetime', '>', $today)
        ->orderBy('start_datetime')
        ->limit(5)
        ->get();
});

$todayAppointmentsWithTravelTime = computed(function () {
    $appointments = $this->todayAppointments;
    $appointmentsWithTravel = collect();

    foreach ($appointments as $index => $appointment) {
        // オブジェクトのまま保持し、追加プロパティを設定
        $appointmentWithTravel = clone $appointment;

        // 次のアポイントメントがある場合、移動時間を計算
        if ($index < $appointments->count() - 1) {
            $nextAppointment = $appointments[$index + 1];
            $currentEndTime = Carbon::parse($appointment->end_datetime);
            $nextStartTime = Carbon::parse($nextAppointment->start_datetime);

            // 移動時間を計算（分単位）
            $travelTimeMinutes = $currentEndTime->diffInMinutes($nextStartTime);
            $travelHours = intval($travelTimeMinutes / 60);
            $travelMinutes = $travelTimeMinutes % 60;

            $appointmentWithTravel->travel_time_minutes = $travelTimeMinutes;
            $appointmentWithTravel->travel_hours = $travelHours;
            $appointmentWithTravel->travel_minutes = $travelMinutes;
            $appointmentWithTravel->next_client = $nextAppointment->client->name;
        } else {
            $appointmentWithTravel->travel_time_minutes = null;
            $appointmentWithTravel->travel_hours = null;
            $appointmentWithTravel->travel_minutes = null;
            $appointmentWithTravel->next_client = null;
        }

        $appointmentsWithTravel->push($appointmentWithTravel);
    }

    return $appointmentsWithTravel;
});

?>

<div class="min-h-screen bg-gray-100 dark:bg-gray-900">
    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center">
                <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">営業管理システム</h1>
                <div class="flex space-x-2">
                    <button wire:click="$set('activeTab', 'today')" @class([
                        'px-4 py-2 rounded-md text-sm font-medium transition-colors',
                        'bg-blue-600 text-white shadow-sm' => $activeTab === 'today',
                        'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 border border-gray-300 dark:border-gray-600' =>
                            $activeTab !== 'today',
                    ])>
                        今日の予定
                    </button>
                    <button wire:click="$set('activeTab', 'clients')" @class([
                        'px-4 py-2 rounded-md text-sm font-medium transition-colors',
                        'bg-blue-600 text-white shadow-sm' => $activeTab === 'clients',
                        'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 border border-gray-300 dark:border-gray-600' =>
                            $activeTab !== 'clients',
                    ])>
                        会社一覧
                    </button>
                    <!-- ルート作成はデスクトップのみ表示 -->
                    <button wire:click="$set('activeTab', 'route')" @class([
                        'px-4 py-2 rounded-md text-sm font-medium transition-colors hidden lg:block',
                        'bg-blue-600 text-white shadow-sm' => $activeTab === 'route',
                        'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 border border-gray-300 dark:border-gray-600' =>
                            $activeTab !== 'route',
                    ])>
                        ルート作成
                    </button>
                </div>
            </div>

            <div class="mt-6">
                @if ($activeTab === 'today')
                    <!-- 今日の予定タブ -->
                    <div class="space-y-6">
                        <!-- 今日の予定セクション -->
                        <div class="bg-white dark:bg-gray-800 shadow rounded-lg">
                            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                                <h2 class="text-lg font-medium text-gray-900 dark:text-white">
                                    今日の予定 ({{ \Carbon\Carbon::today()->format('Y年m月d日') }})
                                </h2>
                            </div>
                            <div class="p-6">
                                @if ($this->todayAppointments->count() > 0)
                                    <div class="space-y-4">
                                        @foreach ($this->todayAppointmentsWithTravelTime as $index => $appointment)
                                            <div
                                                class="flex items-center justify-between p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-800">
                                                <div class="flex-1">
                                                    <div class="flex items-center space-x-3">
                                                        <div class="flex-shrink-0">
                                                            <div class="w-3 h-3 bg-blue-500 rounded-full"></div>
                                                        </div>
                                                        <div>
                                                            <h3
                                                                class="text-sm font-medium text-gray-900 dark:text-white">
                                                                {{ $appointment->title }}
                                                            </h3>
                                                            <p class="text-sm text-gray-600 dark:text-gray-300">
                                                                {{ $appointment->client->name }}
                                                            </p>
                                                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                                                {{ \Carbon\Carbon::parse($appointment->start_datetime)->format('H:i') }}
                                                                -
                                                                {{ \Carbon\Carbon::parse($appointment->end_datetime)->format('H:i') }}
                                                            </p>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="flex items-center space-x-3">
                                                    <div class="text-right">
                                                        <p class="text-sm font-medium text-gray-900 dark:text-white">
                                                            {{ \Carbon\Carbon::parse($appointment->start_datetime)->format('H:i') }}
                                                        </p>
                                                        <p class="text-xs text-gray-500 dark:text-gray-400">
                                                            {{ $appointment->visit_type }}
                                                        </p>
                                                    </div>
                                                    <a href="{{ route('visits.create', ['clientId' => $appointment->client_id]) }}?appointment_id={{ $appointment->id }}"
                                                        class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded-md text-blue-700 bg-blue-100 hover:bg-blue-200 dark:bg-blue-900/30 dark:text-blue-300 dark:hover:bg-blue-900/50 transition-colors">
                                                        詳細
                                                    </a>
                                                </div>
                                            </div>

                                            <!-- 移動時間表示（最後のアポイントメント以外） -->
                                            @if ($appointment->travel_time_minutes !== null)
                                                <div class="flex items-center justify-center py-2">
                                                    <div
                                                        class="flex items-center space-x-2 px-4 py-2 bg-orange-50 dark:bg-orange-900/20 rounded-lg border border-orange-200 dark:border-orange-800">
                                                        <svg class="w-4 h-4 text-orange-600 dark:text-orange-400"
                                                            fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"></path>
                                                        </svg>
                                                        <span
                                                            class="text-sm font-medium text-orange-700 dark:text-orange-300">
                                                            移動:
                                                            @if ($appointment->travel_hours > 0)
                                                                {{ $appointment->travel_hours }}時間
                                                            @endif
                                                            @if ($appointment->travel_minutes > 0)
                                                                {{ $appointment->travel_minutes }}分
                                                            @endif
                                                            @if ($appointment->travel_hours == 0 && $appointment->travel_minutes == 0)
                                                                0分
                                                            @endif
                                                        </span>
                                                    </div>
                                                </div>
                                            @endif
                                        @endforeach
                                    </div>
                                @else
                                    <div class="text-center py-8">
                                        <div class="text-gray-400 dark:text-gray-500 mb-2">
                                            <svg class="mx-auto h-12 w-12" fill="none" viewBox="0 0 24 24"
                                                stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                            </svg>
                                        </div>
                                        <p class="text-gray-500 dark:text-gray-400">今日の予定はありません</p>
                                    </div>
                                @endif
                            </div>
                        </div>


                        <!-- クイックアクション -->
                        <div class="bg-white dark:bg-gray-800 shadow rounded-lg">
                            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                                <h2 class="text-lg font-medium text-gray-900 dark:text-white">クイックアクション</h2>
                            </div>
                            <div class="p-6">
                                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                                    <a href="{{ route('clients.create') }}"
                                        class="flex items-center p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg hover:bg-blue-100 dark:hover:bg-blue-900/30 transition-colors">
                                        <div class="flex-shrink-0">
                                            <svg class="h-6 w-6 text-blue-600 dark:text-blue-400" fill="none"
                                                viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                            </svg>
                                        </div>
                                        <div class="ml-3">
                                            <p class="text-sm font-medium text-blue-900 dark:text-blue-100">訪問先を追加</p>
                                        </div>
                                    </a>
                                    <a href="{{ route('appointments.create') }}"
                                        class="flex items-center p-4 bg-green-50 dark:bg-green-900/20 rounded-lg hover:bg-green-100 dark:hover:bg-green-900/30 transition-colors">
                                        <div class="flex-shrink-0">
                                            <svg class="h-6 w-6 text-green-600 dark:text-green-400" fill="none"
                                                viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                            </svg>
                                        </div>
                                        <div class="ml-3">
                                            <p class="text-sm font-medium text-green-900 dark:text-green-100">アポイントメント追加
                                            </p>
                                        </div>
                                    </a>
                                    <a href="{{ route('appointments.index') }}"
                                        class="flex items-center p-4 bg-purple-50 dark:bg-purple-900/20 rounded-lg hover:bg-purple-100 dark:hover:bg-purple-900/30 transition-colors">
                                        <div class="flex-shrink-0">
                                            <svg class="h-6 w-6 text-purple-600 dark:text-purple-400" fill="none"
                                                viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                            </svg>
                                        </div>
                                        <div class="ml-3">
                                            <p class="text-sm font-medium text-purple-900 dark:text-purple-100">
                                                アポイントメント一覧</p>
                                        </div>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                @elseif ($activeTab === 'clients')
                    <div class="mb-4 flex justify-end">
                        <livewire:client-create />
                    </div>
                    <div class="grid grid-cols-1 gap-6">
                        <div>
                            <livewire:clientlist />
                        </div>
                    </div>
                @elseif($activeTab === 'route')
                    <livewire:routemap />
                @endif
            </div>

            @if ($selectedClient)
                <div class="mt-6">
                    <livewire:clientmemo :client="$selectedClient" />
                </div>
            @endif
        </div>
    </div>
</div>
