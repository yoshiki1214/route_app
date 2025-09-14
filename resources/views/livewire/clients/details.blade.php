<?php

use function Livewire\Volt\{state, mount};
use App\Models\Client;
use App\Models\Visit;
use App\Models\Memo;
use Carbon\Carbon;

state([
    'client' => null,
    'visits' => [],
    'memos' => [],
    'activeTab' => 'visits',
]);

mount(function ($clientId = null) {
    if ($clientId) {
        $this->client = Client::find($clientId);
        if ($this->client) {
            $this->visits = $this->client->visits()->latest('visited_at')->get();
            $this->memos = $this->client->memos()->latest()->get();
        }
    }
});

$getLastVisitDays = function () {
    if (!$this->client || $this->visits->isEmpty()) {
        return null;
    }
    return (int) Carbon::parse($this->visits->first()->visited_at)->diffInDays(now());
};

$setActiveTab = function ($tab) {
    $this->activeTab = $tab;
};

$deleteVisit = function ($visitId) {
    $visit = Visit::find($visitId);
    if ($visit) {
        $visit->delete();
        // 訪問履歴を再取得
        $this->visits = $this->client->visits()->latest('visited_at')->get();
        session()->flash('success', '訪問記録が削除されました。');
    }
};

$deleteClient = function () {
    if ($this->client) {
        // 関連する訪問記録とメモを削除
        $this->client->visits()->delete();
        $this->client->memos()->delete();
        $this->client->delete();

        session()->flash('success', '会社情報が削除されました。');
        $this->redirect(route('dashboard'));
    }
};

?>

<div class="min-h-screen bg-gray-100">
    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            @if ($this->client)
                <!-- ヘッダー -->
                <div class="mb-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <h1 class="text-2xl font-semibold text-gray-900">{{ $this->client->name }}</h1>
                            <a href="https://maps.google.com/maps?q={{ urlencode($this->client->address) }}"
                                target="_blank" class="text-sm text-blue-600 hover:text-blue-800 underline">
                                {{ $this->client->address }}
                            </a>
                        </div>
                        <div class="flex space-x-3">
                            <a href="{{ route('appointments.create', ['client_id' => $this->client->id]) }}"
                                class="client-button-primary">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z">
                                    </path>
                                </svg>
                                アポイントメント追加
                            </a>
                            <a href="{{ route('visits.create', $this->client->id) }}" class="client-button-primary">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 4v16m8-8H4"></path>
                                </svg>
                                訪問記録を追加
                            </a>
                            <a href="{{ route('dashboard') }}" class="client-button-primary">
                                ← 一覧に戻る
                            </a>
                            <button wire:click="deleteClient" wire:confirm="この会社の情報を削除しますか？\n関連する訪問記録とメモもすべて削除されます。"
                                class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                                会社を削除
                            </button>
                        </div>
                    </div>
                </div>


                <!-- タブナビゲーション -->
                <div class="bg-white rounded-lg shadow">
                    <div class="border-b border-gray-200">
                        <nav class="-mb-px flex space-x-8 px-6">
                            <button wire:click="setActiveTab('visits')" @class([
                                'py-4 px-1 border-b-2 font-medium text-sm',
                                'border-blue-500 text-blue-600' => $activeTab === 'visits',
                                'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' =>
                                    $activeTab !== 'visits',
                            ])>
                                訪問履歴 ({{ $this->visits->count() }})
                            </button>
                            <button wire:click="setActiveTab('memos')" @class([
                                'py-4 px-1 border-b-2 font-medium text-sm',
                                'border-blue-500 text-blue-600' => $activeTab === 'memos',
                                'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' =>
                                    $activeTab !== 'memos',
                            ])>
                                メモ ({{ $this->memos->count() }})
                            </button>
                            <button wire:click="setActiveTab('basic')" @class([
                                'py-4 px-1 border-b-2 font-medium text-sm',
                                'border-blue-500 text-blue-600' => $activeTab === 'basic',
                                'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' =>
                                    $activeTab !== 'basic',
                            ])>
                                基本情報
                            </button>
                        </nav>
                    </div>

                    <div class="p-6">
                        @if ($activeTab === 'visits')
                            <!-- 訪問履歴タブ -->
                            <div class="space-y-4">
                                @forelse ($this->visits as $visit)
                                    <div class="border border-gray-200 rounded-lg p-4">
                                        <div class="flex justify-between items-start">
                                            <div>
                                                <h3 class="text-sm font-medium text-gray-900">
                                                    {{ $visit->visit_type }}
                                                </h3>
                                                <p class="text-sm text-gray-500">
                                                    {{ Carbon::parse($visit->visited_at)->format('Y年m月d日 H:i') }}
                                                </p>
                                                @if ($visit->notes)
                                                    <p class="mt-2 text-sm text-gray-700">{{ $visit->notes }}</p>
                                                @endif
                                            </div>
                                            <div class="flex items-center space-x-2">
                                                <span @class([
                                                    'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium',
                                                    'bg-green-100 text-green-800' => $visit->status === '完了',
                                                    'bg-yellow-100 text-yellow-800' => $visit->status === '予定',
                                                    'bg-red-100 text-red-800' => $visit->status === 'キャンセル',
                                                    'bg-gray-100 text-gray-800' => $visit->status === '延期',
                                                ])>
                                                    {{ $visit->status }}
                                                </span>
                                                <a href="{{ route('visits.edit', $visit->id) }}"
                                                    class="text-blue-600 hover:text-blue-800 text-sm">
                                                    編集
                                                </a>
                                                <button wire:click="deleteVisit({{ $visit->id }})"
                                                    wire:confirm="この訪問記録を削除しますか？"
                                                    class="text-red-600 hover:text-red-800 text-sm">
                                                    削除
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                @empty
                                    <div class="text-center py-8">
                                        <p class="text-gray-500">訪問履歴がありません</p>
                                    </div>
                                @endforelse
                            </div>
                        @elseif ($activeTab === 'memos')
                            <!-- メモタブ -->
                            <div class="space-y-4">
                                @forelse ($this->memos as $memo)
                                    <div class="border border-gray-200 rounded-lg p-4">
                                        <div class="flex justify-between items-start">
                                            <div>
                                                <h3 class="text-sm font-medium text-gray-900">
                                                    {{ $memo->title }}
                                                    @if ($memo->is_important)
                                                        <span
                                                            class="ml-2 inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                                            重要
                                                        </span>
                                                    @endif
                                                </h3>
                                                <p class="text-sm text-gray-500">
                                                    {{ Carbon::parse($memo->created_at)->format('Y年m月d日 H:i') }}
                                                </p>
                                                <p class="mt-2 text-sm text-gray-700">{{ $memo->content }}</p>
                                                @if ($memo->category)
                                                    <span
                                                        class="mt-2 inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                        {{ $memo->category }}
                                                    </span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                @empty
                                    <div class="text-center py-8">
                                        <p class="text-gray-500">メモがありません</p>
                                    </div>
                                @endforelse
                            </div>
                        @elseif ($activeTab === 'basic')
                            <!-- 基本情報タブ -->
                            <div class="space-y-6">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <h3 class="text-sm font-medium text-gray-500 mb-3">連絡先情報</h3>
                                        <div class="space-y-3">
                                            @if ($this->client->phone)
                                                <div class="flex items-center">
                                                    <span class="text-sm text-gray-500 w-20">電話:</span>
                                                    <a href="tel:{{ $this->client->phone }}"
                                                        class="text-sm text-blue-600 hover:text-blue-800">
                                                        {{ $this->client->phone }}
                                                    </a>
                                                </div>
                                            @endif
                                            @if ($this->client->email)
                                                <div class="flex items-center">
                                                    <span class="text-sm text-gray-500 w-20">メール:</span>
                                                    <a href="mailto:{{ $this->client->email }}"
                                                        class="text-sm text-blue-600 hover:text-blue-800">
                                                        {{ $this->client->email }}
                                                    </a>
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                    <div>
                                        <h3 class="text-sm font-medium text-gray-500 mb-3">担当者情報</h3>
                                        <div class="space-y-3">
                                            @if ($this->client->contact_person)
                                                <div class="flex items-center">
                                                    <span class="text-sm text-gray-500 w-20">担当者:</span>
                                                    <span
                                                        class="text-sm text-gray-900">{{ $this->client->contact_person }}</span>
                                                </div>
                                            @endif
                                            @if ($this->client->department)
                                                <div class="flex items-center">
                                                    <span class="text-sm text-gray-500 w-20">部署:</span>
                                                    <span
                                                        class="text-sm text-gray-900">{{ $this->client->department }}</span>
                                                </div>
                                            @endif
                                            @if ($this->client->position)
                                                <div class="flex items-center">
                                                    <span class="text-sm text-gray-500 w-20">役職:</span>
                                                    <span
                                                        class="text-sm text-gray-900">{{ $this->client->position }}</span>
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                @if ($this->client->notes)
                                    <div>
                                        <h3 class="text-sm font-medium text-gray-500 mb-3">備考</h3>
                                        <p class="text-sm text-gray-900 bg-gray-50 p-4 rounded-lg">
                                            {{ $this->client->notes }}</p>
                                    </div>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>
            @else
                <!-- クライアントが見つからない場合 -->
                <div class="text-center py-12">
                    <h1 class="text-2xl font-semibold text-gray-900">クライアントが見つかりません</h1>
                    <p class="mt-2 text-gray-500">指定されたクライアントは存在しないか、削除されています。</p>
                    <div class="mt-6">
                        <a href="{{ route('dashboard') }}" class="client-button-primary">
                            一覧に戻る
                        </a>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
