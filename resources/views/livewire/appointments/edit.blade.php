<?php

use function Livewire\Volt\{state, rules, computed, mount};
use App\Models\Client;
use App\Models\Appointment;
use Carbon\Carbon;

state([
    'appointment' => null,
    'clients' => fn() => Client::all(),
    'selected_client_id' => '',
    'title' => '',
    'visit_type' => '訪問',
    'start_datetime' => '',
    'end_datetime' => '',
    'memo' => '',
]);

mount(function ($appointmentId = null) {
    if ($appointmentId) {
        $this->appointment = Appointment::find($appointmentId);
        if ($this->appointment) {
            $this->selected_client_id = $this->appointment->client_id;
            $this->title = $this->appointment->title;
            $this->visit_type = $this->appointment->visit_type;
            $this->start_datetime = $this->appointment->start_datetime->format('Y-m-d\TH:i');
            $this->end_datetime = $this->appointment->end_datetime->format('Y-m-d\TH:i');
            $this->memo = $this->appointment->memo;
        }
    }
});

rules([
    'selected_client_id' => 'required|exists:clients,id',
    'title' => 'required|string|max:255',
    'visit_type' => 'required|string|max:255',
    'start_datetime' => 'required|date',
    'end_datetime' => 'required|date|after:start_datetime',
    'memo' => 'nullable|string|max:1000',
]);

$save = function () {
    $validated = $this->validate();

    $this->appointment->update([
        'client_id' => $validated['selected_client_id'],
        'title' => $validated['title'],
        'visit_type' => $validated['visit_type'],
        'start_datetime' => $validated['start_datetime'],
        'end_datetime' => $validated['end_datetime'],
        'memo' => $validated['memo'],
    ]);

    return redirect()->route('appointments.index')->with('success', 'アポイントメントが正常に更新されました。');
};

$selectedClient = computed(function () {
    if (!$this->selected_client_id) {
        return null;
    }
    return Client::find($this->selected_client_id);
});

?>

<div class="page-container">
    <div class="page-content">
        <!-- ヘッダー -->
        <div class="page-header">
            <div class="page-header-content">
                <div>
                    <h1 class="page-title">アポイントメント編集</h1>
                    <p class="page-subtitle">アポイントメントの詳細を編集します</p>
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

        @if ($this->appointment)
            <!-- フォーム -->
            <div class="card">
                <form wire:submit="save" class="card-content">
                    <div class="form-group">
                        <!-- クライアント選択 -->
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
                                                <span
                                                    class="client-info-value">{{ $this->selectedClient->address }}</span>
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
                            <span wire:loading.remove>更新</span>
                            <span wire:loading>更新中...</span>
                        </button>
                    </div>
                </form>
            </div>
        @else
            <!-- アポイントメントが見つからない場合 -->
            <div class="card">
                <div class="card-content text-center py-12">
                    <svg class="w-12 h-12 mx-auto text-gray-400 dark:text-gray-500 mb-4" fill="none"
                        stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z">
                        </path>
                    </svg>
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">アポイントメントが見つかりません</h3>
                    <p class="text-gray-500 dark:text-gray-400 mb-4">
                        指定されたアポイントメントは存在しないか、削除されています。
                    </p>
                    <a href="{{ route('appointments.index') }}" class="button-primary">
                        アポイントメント一覧に戻る
                    </a>
                </div>
            </div>
        @endif
    </div>
</div>
