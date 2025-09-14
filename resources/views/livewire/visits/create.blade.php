<?php

use function Livewire\Volt\{state, rules, mount, computed};
use App\Models\Client;
use App\Models\Visit;
use App\Models\Appointment;
use Carbon\Carbon;

state([
    'client' => null,
    'appointment' => null,
    'visit_type' => '訪問',
    'visited_at' => '',
    'status' => '完了',
    'notes' => '',
]);

rules([
    'visit_type' => 'required|string|max:100',
    'visited_at' => 'required|date',
    'status' => 'required|string|in:完了,予定,キャンセル,延期',
    'notes' => 'nullable|string|max:1000',
]);

mount(function ($clientId = null) {
    if ($clientId) {
        $this->client = Client::find($clientId);
        if ($this->client) {
            // デフォルト値を設定
            $this->visited_at = now()->format('Y-m-d\TH:i');
        }
    }

    // アポイントメントIDがクエリパラメータで渡された場合
    $appointmentId = request()->query('appointment_id');
    if ($appointmentId) {
        $this->appointment = Appointment::with('client')->find($appointmentId);
        if ($this->appointment) {
            // アポイントメントの情報を自動入力
            $this->client = $this->appointment->client;
            $this->visit_type = $this->appointment->visit_type;
            $this->visited_at = Carbon::parse($this->appointment->start_datetime)->format('Y-m-d\TH:i');
            $this->notes = $this->appointment->memo ?? '';
        }
    }
});

$save = function () {
    $validated = $this->validate();

    if (!$this->client) {
        return redirect()->route('dashboard')->with('error', 'クライアントが見つかりません。');
    }

    // 訪問記録を作成
    $visit = Visit::create([
        'client_id' => $this->client->id,
        'user_id' => auth()->id(),
        'visit_type' => $validated['visit_type'],
        'visited_at' => $validated['visited_at'],
        'status' => $validated['status'],
        'notes' => $validated['notes'],
        'appointment_id' => $this->appointment?->id,
    ]);

    // クライアント詳細ページにリダイレクト
    return redirect()
        ->route('clients.detail', ['clientId' => $this->client->id])
        ->with('success', '訪問記録が正常に保存されました。');
};

$deleteAppointment = function () {
    if ($this->appointment) {
        $clientId = $this->appointment->client_id;
        $this->appointment->delete();

        return redirect()
            ->route('clients.detail', ['clientId' => $clientId])
            ->with('success', 'アポイントメントが削除されました。');
    }
};

$visitTypes = computed(function () {
    return [
        '訪問' => '訪問',
        '電話' => '電話',
        'オンライン会議' => 'オンライン会議',
        'その他' => 'その他',
    ];
});

$statusOptions = computed(function () {
    return [
        '完了' => '完了',
        '予定' => '予定',
        'キャンセル' => 'キャンセル',
        '延期' => '延期',
    ];
});

?>

<div class="page-container">
    <div class="page-content">
        @if ($this->client)
            <!-- ヘッダー -->
            <div class="page-header">
                <div class="page-header-content">
                    <div>
                        <h1 class="page-title">訪問記録の作成</h1>
                        <p class="page-subtitle">
                            {{ $this->client->name }} への訪問記録を作成します
                        </p>
                    </div>
                    <div class="page-actions">
                        <a href="{{ route('clients.detail', $this->client->id) }}" class="client-button-primary">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                            </svg>
                            クライアント詳細に戻る
                        </a>
                    </div>
                </div>
            </div>

            <!-- クライアント情報カード -->
            <div class="card mb-6">
                <div class="card-header">
                    <h2 class="card-title">クライアント情報</h2>
                    <div class="client-info-grid">
                        <div>
                            <h3 class="client-info-label">基本情報</h3>
                            <div class="client-info-section">
                                <div class="client-info-item">
                                    <span class="client-info-key">会社名:</span>
                                    <span class="client-info-value font-medium">{{ $this->client->name }}</span>
                                </div>
                                <div class="client-info-item">
                                    <span class="client-info-key">住所:</span>
                                    <span class="client-info-value">{{ $this->client->address }}</span>
                                </div>
                            </div>
                        </div>
                        <div>
                            <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">連絡先</h3>
                            <div class="mt-2 space-y-2">
                                @if ($this->client->phone)
                                    <div class="flex items-center">
                                        <span class="text-sm text-gray-500 dark:text-gray-400 w-20">電話:</span>
                                        <a href="tel:{{ $this->client->phone }}"
                                            class="text-sm text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300">
                                            {{ $this->client->phone }}
                                        </a>
                                    </div>
                                @endif
                                @if ($this->client->email)
                                    <div class="flex items-center">
                                        <span class="text-sm text-gray-500 dark:text-gray-400 w-20">メール:</span>
                                        <a href="mailto:{{ $this->client->email }}"
                                            class="text-sm text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300">
                                            {{ $this->client->email }}
                                        </a>
                                    </div>
                                @endif
                                @if ($this->client->contact_person)
                                    <div class="flex items-center">
                                        <span class="text-sm text-gray-500 dark:text-gray-400 w-20">担当者:</span>
                                        <span
                                            class="text-sm text-gray-900 dark:text-white">{{ $this->client->contact_person }}</span>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                <!-- アポイントメント情報カード -->
                @if ($this->appointment)
                    <div class="card mb-6">
                        <div class="card-header">
                            <h2 class="card-title">アポイントメント情報</h2>
                        </div>
                        <div class="card-content">
                            <div class="client-info-grid">
                                <div>
                                    <h3 class="client-info-label">予定詳細</h3>
                                    <div class="client-info-section">
                                        <div class="client-info-item">
                                            <span class="client-info-key">件名:</span>
                                            <span
                                                class="client-info-value font-medium">{{ $this->appointment->title }}</span>
                                        </div>
                                        <div class="client-info-item">
                                            <span class="client-info-key">予定日時:</span>
                                            <span class="client-info-value">
                                                {{ \Carbon\Carbon::parse($this->appointment->start_datetime)->format('Y年m月d日 H:i') }}
                                                -
                                                {{ \Carbon\Carbon::parse($this->appointment->end_datetime)->format('H:i') }}
                                            </span>
                                        </div>
                                        <div class="client-info-item">
                                            <span class="client-info-key">訪問種別:</span>
                                            <span class="client-info-value">{{ $this->appointment->visit_type }}</span>
                                        </div>
                                    </div>
                                </div>
                                @if ($this->appointment->memo)
                                    <div>
                                        <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">メモ</h3>
                                        <div class="mt-2">
                                            <p class="text-sm text-gray-900 dark:text-white">
                                                {{ $this->appointment->memo }}</p>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                @endif

                <!-- フォーム -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-lg">
                    <form wire:submit="save" class="p-6 space-y-6">
                        <!-- 訪問情報 -->
                        <div class="space-y-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white">訪問情報</h3>

                            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                                <!-- 訪問種別 -->
                                <div>
                                    <label for="visit_type"
                                        class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        訪問種別 <span class="text-red-500">*</span>
                                    </label>
                                    <select wire:model.live="visit_type" id="visit_type"
                                        class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                        @foreach ($this->visitTypes as $value => $label)
                                            <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    @error('visit_type')
                                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <!-- 訪問日時 -->
                                <div>
                                    <label for="visited_at"
                                        class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        訪問日時 <span class="text-red-500">*</span>
                                    </label>
                                    <input type="datetime-local" wire:model.live="visited_at" id="visited_at"
                                        class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                    @error('visited_at')
                                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>

                            <!-- ステータス -->
                            <div>
                                <label for="status"
                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    ステータス <span class="text-red-500">*</span>
                                </label>
                                <select wire:model.live="status" id="status"
                                    class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                    @foreach ($this->statusOptions as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('status')
                                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- 訪問メモ -->
                            <div>
                                <label for="notes"
                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300">訪問メモ</label>
                                <textarea wire:model.live="notes" id="notes" rows="4" placeholder="訪問内容、商談内容、今後の予定などを記録してください"
                                    class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm"></textarea>
                                @error('notes')
                                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>


                        <!-- ボタン -->
                        <div
                            class="flex justify-between items-center pt-6 border-t border-gray-200 dark:border-gray-700">
                            <!-- 削除ボタン（アポイントメントがある場合のみ表示） -->
                            @if ($this->appointment)
                                <button type="button" wire:click="deleteAppointment"
                                    wire:confirm="このアポイントメントを削除しますか？"
                                    class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                    アポイントメントを削除
                                </button>
                            @else
                                <div></div>
                            @endif

                            <!-- 保存・キャンセルボタン -->
                            <div class="flex space-x-3">
                                <a href="{{ route('clients.detail', $this->client->id) }}"
                                    class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                    キャンセル
                                </a>
                                <button type="submit" wire:loading.attr="disabled" wire:loading.class="opacity-50"
                                    class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50">
                                    <span wire:loading.remove>保存</span>
                                    <span wire:loading>保存中...</span>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            @else
                <!-- クライアントが見つからない場合 -->
                <div class="text-center py-12">
                    <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">クライアントが見つかりません</h1>
                    <p class="mt-2 text-gray-500 dark:text-gray-400">指定されたクライアントは存在しないか、削除されています。</p>
                    <div class="mt-6">
                        <a href="{{ route('dashboard') }}" class="client-button-primary">
                            一覧に戻る
                        </a>
                    </div>
                </div>
        @endif
    </div>
</div>
