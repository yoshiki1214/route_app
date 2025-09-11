<?php

use function Livewire\Volt\{state, computed};
use App\Models\Appointment;
use Carbon\Carbon;

state([
    'appointments' => fn() => Appointment::with(['client', 'visits'])
        ->get()
        ->filter(function ($appointment) {
            // ステータスが「完了」の訪問記録に関連するアポイントメントは非表示
            $completedVisit = $appointment->visits()->where('status', '完了')->exists();
            return !$completedVisit;
        }),
    'filter' => 'all', // all, today, upcoming, past
]);

$filteredAppointments = computed(function () {
    $appointments = $this->appointments;

    return match ($this->filter) {
        'today' => $appointments->filter(function ($appointment) {
            return Carbon::parse($appointment->start_datetime)->isToday();
        }),
        'upcoming' => $appointments->filter(function ($appointment) {
            return Carbon::parse($appointment->start_datetime)->isFuture();
        }),
        'past' => $appointments->filter(function ($appointment) {
            return Carbon::parse($appointment->start_datetime)->isPast();
        }),
        default => $appointments,
    };
});

$setFilter = function ($filter) {
    $this->filter = $filter;
};

$deleteAppointment = function ($appointmentId) {
    $appointment = Appointment::find($appointmentId);
    if ($appointment) {
        $appointment->delete();
        $this->appointments = Appointment::with(['client', 'visits'])
            ->get()
            ->filter(function ($appointment) {
                // ステータスが「完了」の訪問記録に関連するアポイントメントは非表示
                $completedVisit = $appointment->visits()->where('status', '完了')->exists();
                return !$completedVisit;
            });
        session()->flash('success', 'アポイントメントが削除されました。');
    }
};

?>

<div class="page-container">
    <div class="page-content">
        <!-- ヘッダー -->
        <div class="page-header">
            <div class="page-header-content">
                <div>
                    <h1 class="page-title">アポイントメント</h1>
                    <p class="page-subtitle">訪問予定の管理</p>
                </div>
                <div class="page-actions">
                    <a href="{{ route('appointments.create') }}" class="client-button-primary">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4">
                            </path>
                        </svg>
                        新規アポイントメント
                    </a>
                </div>
            </div>
        </div>

        <!-- フィルター -->
        <div class="card mb-6">
            <div class="card-content">
                <div class="flex flex-wrap gap-2">
                    <button wire:click="setFilter('all')" @class([
                        'client-sort-button',
                        'client-sort-button-active' => $filter === 'all',
                        'client-sort-button-inactive' => $filter !== 'all',
                    ])>
                        すべて
                    </button>
                    <button wire:click="setFilter('today')" @class([
                        'client-sort-button',
                        'client-sort-button-active' => $filter === 'today',
                        'client-sort-button-inactive' => $filter !== 'today',
                    ])>
                        今日
                    </button>
                    <button wire:click="setFilter('upcoming')" @class([
                        'client-sort-button',
                        'client-sort-button-active' => $filter === 'upcoming',
                        'client-sort-button-inactive' => $filter !== 'upcoming',
                    ])>
                        今後の予定
                    </button>
                    <button wire:click="setFilter('past')" @class([
                        'client-sort-button',
                        'client-sort-button-active' => $filter === 'past',
                        'client-sort-button-inactive' => $filter !== 'past',
                    ])>
                        過去の予定
                    </button>
                </div>
            </div>
        </div>

        <!-- アポイントメント一覧 -->
        <div class="space-y-4">
            @forelse ($this->filteredAppointments as $appointment)
                <div class="card">
                    <div class="card-content">
                        <div class="flex justify-between items-start">
                            <div class="flex-1">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                                    {{ $appointment->title }}
                                </h3>
                                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                    {{ $appointment->client->name }}
                                </p>
                                <div class="flex items-center mt-2 text-sm text-gray-500 dark:text-gray-400">
                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z">
                                        </path>
                                    </svg>
                                    {{ Carbon::parse($appointment->start_datetime)->format('Y年m月d日 H:i') }} -
                                    {{ Carbon::parse($appointment->end_datetime)->format('H:i') }}
                                </div>
                                @if ($appointment->memo)
                                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-2">
                                        {{ $appointment->memo }}
                                    </p>
                                @endif
                            </div>
                            <div class="flex space-x-2">
                                <a href="{{ route('clients.detail', $appointment->client->id) }}"
                                    class="client-action-link">
                                    クライアント詳細
                                </a>
                                <a href="{{ route('appointments.edit', $appointment->id) }}"
                                    class="client-action-link-green">
                                    編集
                                </a>
                                <button wire:click="deleteAppointment({{ $appointment->id }})"
                                    wire:confirm="このアポイントメントを削除しますか？関連する訪問履歴も削除されます。" class="client-action-link-red">
                                    削除
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="card">
                    <div class="card-content text-center py-12">
                        <svg class="w-12 h-12 mx-auto text-gray-400 dark:text-gray-500 mb-4" fill="none"
                            stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z">
                            </path>
                        </svg>
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">アポイントメントがありません</h3>
                        <p class="text-gray-500 dark:text-gray-400 mb-4">
                            @if ($filter === 'today')
                                今日のアポイントメントはありません
                            @elseif ($filter === 'upcoming')
                                今後のアポイントメントはありません
                            @elseif ($filter === 'past')
                                過去のアポイントメントはありません
                            @else
                                アポイントメントが登録されていません
                            @endif
                        </p>
                        <a href="{{ route('appointments.create') }}" class="client-button-primary">
                            新規アポイントメントを作成
                        </a>
                    </div>
                </div>
            @endforelse
        </div>
    </div>
</div>
