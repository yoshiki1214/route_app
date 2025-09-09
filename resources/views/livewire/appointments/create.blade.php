<?php

use function Livewire\Volt\{state, rules, computed};
use App\Models\Client;
use App\Models\Appointment;
use Carbon\Carbon;

state([
    'clients' => fn() => Client::all(),
    'selected_client_id' => '',
    'title' => '',
    'start_datetime' => '',
    'end_datetime' => '',
    'memo' => '',
]);

rules([
    'selected_client_id' => 'required|exists:clients,id',
    'title' => 'required|string|max:255',
    'start_datetime' => 'required|date|after:now',
    'end_datetime' => 'required|date|after:start_datetime',
    'memo' => 'nullable|string|max:1000',
]);

$save = function () {
    $validated = $this->validate();

    Appointment::create([
        'client_id' => $validated['selected_client_id'],
        'user_id' => auth()->id(),
        'title' => $validated['title'],
        'start_datetime' => $validated['start_datetime'],
        'end_datetime' => $validated['end_datetime'],
        'memo' => $validated['memo'],
    ]);

    return redirect()->route('appointments.index')->with('success', 'アポイントメントが正常に作成されました。');
};

$selectedClient = computed(function () {
    if (!$this->selected_client_id) {
        return null;
    }
    return $this->clients->find($this->selected_client_id);
});

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
                        <span wire:loading.remove>作成</span>
                        <span wire:loading>作成中...</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
