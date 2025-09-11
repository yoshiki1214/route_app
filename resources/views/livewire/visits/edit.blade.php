<?php

use function Livewire\Volt\{state, rules, computed, mount};
use App\Models\Visit;
use App\Models\Client;
use Carbon\Carbon;

state([
    'visit' => null,
    'client' => null,
    'visit_type' => '訪問',
    'visited_at' => '',
    'status' => '完了',
    'notes' => '',
]);

mount(function ($visitId = null) {
    if ($visitId) {
        $this->visit = Visit::with('client')->find($visitId);
        if ($this->visit) {
            $this->client = $this->visit->client;
            $this->visit_type = $this->visit->visit_type;
            $this->visited_at = $this->visit->visited_at->format('Y-m-d\TH:i');
            $this->status = $this->visit->status;
            $this->notes = $this->visit->notes;
        }
    }
});

rules([
    'visit_type' => 'required|string|max:100',
    'visited_at' => 'required|date',
    'status' => 'required|string|max:50',
    'notes' => 'nullable|string|max:1000',
]);

$save = function () {
    $validated = $this->validate();

    $this->visit->update([
        'visit_type' => $validated['visit_type'],
        'visited_at' => $validated['visited_at'],
        'status' => $validated['status'],
        'notes' => $validated['notes'],
    ]);

    return redirect()
        ->route('clients.detail', ['clientId' => $this->client->id])
        ->with('success', '訪問記録が正常に更新されました。');
};

$deleteVisit = function () {
    if ($this->visit) {
        $clientId = $this->client->id;
        $this->visit->delete();

        return redirect()
            ->route('clients.detail', ['clientId' => $clientId])
            ->with('success', '訪問記録が削除されました。');
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
        @if ($this->visit && $this->client)
            <!-- ヘッダー -->
            <div class="page-header">
                <div class="page-header-content">
                    <div>
                        <h1 class="page-title">訪問記録の編集</h1>
                        <p class="page-subtitle">
                            {{ $this->client->name }} の訪問記録を編集します
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

            <!-- クライアント情報 -->
            <div class="card mb-6">
                <div class="card-content">
                    <h3 class="card-section-title">クライアント情報</h3>
                    <div class="client-info-grid">
                        <div>
                            <h4 class="client-info-label">基本情報</h4>
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
                            <h4 class="client-info-label">連絡先</h4>
                            <div class="client-info-section">
                                @if ($this->client->phone)
                                    <div class="client-info-item">
                                        <span class="client-info-key">電話:</span>
                                        <a href="tel:{{ $this->client->phone }}" class="client-info-link">
                                            {{ $this->client->phone }}
                                        </a>
                                    </div>
                                @endif
                                @if ($this->client->email)
                                    <div class="client-info-item">
                                        <span class="client-info-key">メール:</span>
                                        <a href="mailto:{{ $this->client->email }}" class="client-info-link">
                                            {{ $this->client->email }}
                                        </a>
                                    </div>
                                @endif
                                @if ($this->client->contact_person)
                                    <div class="client-info-item">
                                        <span class="client-info-key">担当者:</span>
                                        <span class="client-info-value">{{ $this->client->contact_person }}</span>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- フォーム -->
            <div class="card">
                <form wire:submit="save" class="card-content">
                    <div class="form-group">
                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                            <!-- 訪問種別 -->
                            <div>
                                <label for="visit_type" class="form-field">
                                    訪問種別 <span class="form-field-required">*</span>
                                </label>
                                <select wire:model.live="visit_type" id="visit_type" class="form-select">
                                    @foreach ($this->visitTypes as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('visit_type')
                                    <p class="form-error">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- 訪問日時 -->
                            <div>
                                <label for="visited_at" class="form-field">
                                    訪問日時 <span class="form-field-required">*</span>
                                </label>
                                <input type="datetime-local" wire:model.live="visited_at" id="visited_at"
                                    class="form-input">
                                @error('visited_at')
                                    <p class="form-error">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- ステータス -->
                            <div>
                                <label for="status" class="form-field">
                                    ステータス <span class="form-field-required">*</span>
                                </label>
                                <select wire:model.live="status" id="status" class="form-select">
                                    @foreach ($this->statusOptions as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('status')
                                    <p class="form-error">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <!-- メモ -->
                        <div class="mt-6">
                            <label for="notes" class="form-field">メモ</label>
                            <textarea wire:model.live="notes" id="notes" rows="4" placeholder="訪問内容、商談結果、次回の予定などを記録してください"
                                class="form-textarea"></textarea>
                            @error('notes')
                                <p class="form-error">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <!-- ボタン -->
                    <div class="button-group">
                        <a href="{{ route('clients.detail', $this->client->id) }}" class="button-secondary">
                            キャンセル
                        </a>
                        <button type="button" wire:click="deleteVisit" wire:confirm="この訪問記録を削除しますか？"
                            wire:loading.attr="disabled" wire:loading.class="opacity-50" class="button-danger">
                            <span wire:loading.remove>削除</span>
                            <span wire:loading>削除中...</span>
                        </button>
                        <button type="submit" wire:loading.attr="disabled" wire:loading.class="opacity-50"
                            class="button-primary">
                            <span wire:loading.remove>更新</span>
                            <span wire:loading>更新中...</span>
                        </button>
                    </div>
                </form>
            </div>
        @else
            <!-- 訪問記録が見つからない場合 -->
            <div class="card">
                <div class="card-content text-center py-12">
                    <svg class="w-12 h-12 mx-auto text-gray-400 dark:text-gray-500 mb-4" fill="none"
                        stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4">
                        </path>
                    </svg>
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">訪問記録が見つかりません</h3>
                    <p class="text-gray-500 dark:text-gray-400 mb-4">
                        指定された訪問記録は存在しないか、削除されています。
                    </p>
                    <a href="{{ route('dashboard') }}" class="button-primary">
                        ダッシュボードに戻る
                    </a>
                </div>
            </div>
        @endif
    </div>
</div>
