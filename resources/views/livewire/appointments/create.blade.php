<?php

use function Livewire\Volt\{state, rules, computed, mount, updated};
use App\Models\Client;
use App\Models\Appointment;
use App\Models\Visit;
use Carbon\Carbon;

// State definitions
state([
    'clients' => fn() => Client::whereNotNull('name')->get(),
    'selected_client_id' => '',
    'title' => '',
    'visit_type' => '訪問',
    'start_datetime' => '',
    'end_datetime' => '',
    'memo' => '',
    'defaultDuration' => 60, // デフォルト60分
]);

// Computed properties
$selectedClient = computed(function () {
    if (!$this->selected_client_id) {
        return null;
    }
    $client = Client::find($this->selected_client_id);
    if (!$client) {
        // クライアントが見つからない場合、selected_client_idをリセット
        $this->selected_client_id = '';
        return null;
    }
    return $client;
});

// Lifecycle hooks
mount(function ($client_id = null) {
    if ($client_id) {
        $this->selected_client_id = $client_id;
    }

    // セッションからデフォルト滞在時間を取得
    $this->defaultDuration = session('appointment_default_duration', 60);
});

// 開始時間が変更されたときに終了時間を自動設定
updated([
    'start_datetime' => function () {
        if ($this->start_datetime) {
            $startTime = Carbon::parse($this->start_datetime);
            $endTime = $startTime->copy()->addMinutes($this->defaultDuration);
            $this->end_datetime = $endTime->format('Y-m-d\TH:i');
        }
    },
]);

rules(
    [
        'selected_client_id' => 'required|exists:clients,id',
        'title' => 'required|string|max:255',
        'visit_type' => 'required|string|max:255',
        'start_datetime' => 'required|date|after:now',
        'end_datetime' => 'required|date|after:start_datetime',
        'memo' => 'nullable|string|max:1000',
    ],
    [
        'selected_client_id.required' => 'クライアントを選択してください',
        'selected_client_id.exists' => '選択されたクライアントが存在しません',
        'title.required' => '件名を入力してください',
        'title.max' => '件名は255文字以内で入力してください',
        'visit_type.required' => '訪問種別を選択してください',
        'start_datetime.required' => '開始日時を入力してください',
        'start_datetime.after' => '開始日時は現在時刻より後を指定してください',
        'end_datetime.required' => '終了日時を入力してください',
        'end_datetime.after' => '終了日時は開始日時より後を指定してください',
        'memo.max' => 'メモは1000文字以内で入力してください',
    ],
);

$save = function () {
    try {
        \Log::info('Starting appointment creation...', [
            'selected_client_id' => $this->selected_client_id,
            'title' => $this->title,
            'start_datetime' => $this->start_datetime,
            'end_datetime' => $this->end_datetime,
        ]);

        $validated = $this->validate();

        // クライアントの存在確認
        $client = Client::find($validated['selected_client_id']);
        if (!$client) {
            \Log::error('Client not found', ['client_id' => $validated['selected_client_id']]);
            session()->flash('error', '選択されたクライアントが見つかりません。');
            return;
        }

        \Log::info('Creating appointment for client', [
            'client_id' => $client->id,
            'client_name' => $client->name,
        ]);

        $appointment = Appointment::create([
            'client_id' => $validated['selected_client_id'],
            'user_id' => auth()->id(),
            'title' => $validated['title'],
            'visit_type' => $validated['visit_type'],
            'start_datetime' => $validated['start_datetime'],
            'end_datetime' => $validated['end_datetime'],
            'memo' => $validated['memo'],
        ]);

        \Log::info('Appointment created:', [
            'appointment_id' => $appointment->id,
            'client_id' => $appointment->client_id,
            'title' => $appointment->title,
        ]);

        try {
            // アポイントメントを訪問履歴に予定として追加
            $visit = Visit::create([
                'client_id' => $validated['selected_client_id'],
                'user_id' => auth()->id(),
                'visit_type' => $validated['visit_type'],
                'visited_at' => $validated['start_datetime'],
                'status' => '予定',
                'notes' => $validated['memo'] ? "アポイントメント: {$validated['title']}\n{$validated['memo']}" : "アポイントメント: {$validated['title']}",
                'appointment_id' => $appointment->id, // アポイントメントとの関連付け
            ]);

            \Log::info('Visit record created:', [
                'visit_id' => $visit->id,
                'appointment_id' => $appointment->id,
            ]);

            \Log::info('Redirecting to created page:', [
                'appointment_id' => $appointment->id,
                'route' => 'appointments.created',
            ]);

            return redirect()->route('appointments.created', ['appointmentId' => $appointment->id]);
        } catch (\Exception $e) {
            \Log::error('Error creating visit record:', [
                'error' => $e->getMessage(),
                'appointment_id' => $appointment->id,
            ]);

            // アポイントメントを削除（ロールバック）
            $appointment->delete();

            session()->flash('error', 'アポイントメントの作成中にエラーが発生しました。');
            return;
        }
    } catch (\Exception $e) {
        \Log::error('Error in appointment creation:', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        session()->flash('error', 'アポイントメントの作成中にエラーが発生しました。' . ($e->getMessage() ? "（{$e->getMessage()}）" : ''));
        return;
    }
};

?>

<div class="page-container">
    <div class="page-content">
        <!-- ヘッダー -->
        <div class="page-header">
            <div class="page-header-content">
                <div>
                    <h1 class="page-title">新規アポイントメント</h1>
                    <p class="page-subtitle">訪問予定を作成します</p>
                </div>
                <div class="page-actions">
                    <a href="{{ route('appointments.index') }}" class="client-button-primary">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                        </svg>
                        アポイントメント一覧に戻る
                    </a>
                </div>
            </div>
        </div>

        <!-- フォーム -->
        <div class="card">
            <form wire:submit.prevent="save" class="card-content">
                <div class="form-group">
                    <!-- クライアント選択 -->
                    @if (!$this->selected_client_id)
                        <div>
                            <label for="selected_client_id" class="form-field">
                                クライアント <span class="form-field-required">*</span>
                            </label>
                            <select wire:model.live="selected_client_id" id="selected_client_id" class="form-select">
                                <option value="">クライアントを選択してください</option>
                                @foreach ($this->clients as $client)
                                    <option value="{{ $client->id }}">{{ $client->name }}</option>
                                @endforeach
                            </select>
                            @error('selected_client_id')
                                <p class="form-error">{{ $message }}</p>
                            @enderror
                        </div>
                    @else
                        <!-- クライアントが既に選択されている場合 -->
                        <div>
                            <label for="client_name" class="form-field">
                                クライアント <span class="form-field-required">*</span>
                            </label>
                            <input type="text" id="client_name" value="{{ $this->selectedClient?->name }}"
                                class="form-input bg-gray-50 dark:bg-gray-700" readonly>
                            <input type="hidden" wire:model="selected_client_id">
                        </div>
                    @endif

                    @if ($this->selectedClient)
                        <!-- 選択されたクライアント情報 -->
                        <div class="card-section-divider">
                            <h3 class="card-section-title">クライアント情報</h3>
                            <div class="client-info-grid">
                                <div>
                                    <h4 class="client-info-label">基本情報</h4>
                                    <div class="client-info-section">
                                        <div class="client-info-item">
                                            <span class="client-info-key">会社名:</span>
                                            <span
                                                class="client-info-value font-medium">{{ $this->selectedClient->name }}</span>
                                        </div>
                                        <div class="client-info-item">
                                            <span class="client-info-key">住所:</span>
                                            <span class="client-info-value">{{ $this->selectedClient->address }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div>
                                    <h4 class="client-info-label">連絡先</h4>
                                    <div class="client-info-section">
                                        @if ($this->selectedClient->phone)
                                            <div class="client-info-item">
                                                <span class="client-info-key">電話:</span>
                                                <a href="tel:{{ $this->selectedClient->phone }}"
                                                    class="client-info-link">
                                                    {{ $this->selectedClient->phone }}
                                                </a>
                                            </div>
                                        @endif
                                        @if ($this->selectedClient->email)
                                            <div class="client-info-item">
                                                <span class="client-info-key">メール:</span>
                                                <a href="mailto:{{ $this->selectedClient->email }}"
                                                    class="client-info-link">
                                                    {{ $this->selectedClient->email }}
                                                </a>
                                            </div>
                                        @endif
                                        @if ($this->selectedClient->contact_person)
                                            <div class="client-info-item">
                                                <span class="client-info-key">担当者:</span>
                                                <span
                                                    class="client-info-value">{{ $this->selectedClient->contact_person }}</span>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif

                    <!-- アポイントメント情報 -->
                    <div class="card-section">
                        <h3 class="card-section-title">アポイントメント情報</h3>

                        <!-- 件名 -->
                        <div>
                            <label for="title" class="form-field">
                                件名 <span class="form-field-required">*</span>
                            </label>
                            <input type="text" wire:model.live="title" id="title" placeholder="例: 新商品のご提案"
                                class="form-input">
                            @error('title')
                                <p class="form-error">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- 訪問種別 -->
                        <div>
                            <label for="visit_type" class="form-field">
                                訪問種別 <span class="form-field-required">*</span>
                            </label>
                            <select wire:model.live="visit_type" id="visit_type" class="form-select">
                                <option value="訪問">訪問</option>
                                <option value="電話">電話</option>
                                <option value="オンライン会議">オンライン会議</option>
                                <option value="その他">その他</option>
                            </select>
                            @error('visit_type')
                                <p class="form-error">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- 日時 -->
                        <div class="form-grid">
                            <div>
                                <label for="start_datetime" class="form-field">
                                    開始日時 <span class="form-field-required">*</span>
                                </label>
                                <input type="datetime-local" wire:model.live="start_datetime" id="start_datetime"
                                    class="form-input">
                                @error('start_datetime')
                                    <p class="form-error">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="end_datetime" class="form-field">
                                    終了日時 <span class="form-field-required">*</span>
                                </label>
                                <input type="datetime-local" wire:model.live="end_datetime" id="end_datetime"
                                    class="form-input">
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                    開始時間を入力すると、設定された滞在時間（{{ $this->defaultDuration }}分）が自動で加算されます。
                                </p>
                                @error('end_datetime')
                                    <p class="form-error">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <!-- メモ -->
                        <div>
                            <label for="memo" class="form-field">メモ</label>
                            <textarea wire:model.live="memo" id="memo" rows="4" placeholder="持参物、準備事項、注意点などを記録してください"
                                class="form-textarea"></textarea>
                            @error('memo')
                                <p class="form-error">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                <!-- ボタン -->
                <div class="button-group">
                    <a href="{{ route('appointments.index') }}" class="button-secondary">
                        キャンセル
                    </a>
                    <button type="submit" wire:loading.attr="disabled" wire:loading.class="opacity-50"
                        class="button-primary">
                        <span wire:loading.remove>作成</span>
                        <span wire:loading>作成中...</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
