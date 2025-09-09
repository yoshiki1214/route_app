<?php

use function Livewire\Volt\{state, rules};
use App\Models\Client;

state([
    'name' => '',
    'address' => '',
    'latitude' => null,
    'longitude' => null,
    'phone' => '',
    'email' => '',
    'contact_person' => '',
    'department' => '',
    'position' => '',
    'notes' => '',
    'googleMapsUrl' => '',
    'isProcessing' => false,
]);

rules([
    'name' => 'required|string|max:255',
    'address' => 'required|string|max:255',
    'phone' => 'nullable|string|max:20',
    'email' => 'nullable|email|max:255',
    'contact_person' => 'nullable|string|max:255',
    'department' => 'nullable|string|max:100',
    'position' => 'nullable|string|max:100',
    'notes' => 'nullable|string|max:1000',
    'googleMapsUrl' => 'nullable|string|max:1000',
]);

$save = function () {
    $validated = $this->validate();

    // 住所から緯度経度を取得（実際のプロジェクトではGoogleマップAPIなどを使用）
    $validated['latitude'] = 35.6812362; // 仮の値
    $validated['longitude'] = 139.7671248; // 仮の値

    $client = Client::create($validated);

    $this->reset();

    // 作成後にクライアント詳細ページにリダイレクト
    return redirect()
        ->route('clients.detail', ['clientId' => $client->id])
        ->with('success', '訪問先が正常に追加されました。');
};

$extractFromGoogleMaps = function () {
    $this->isProcessing = true;

    try {
        $url = $this->googleMapsUrl;

        // Google MapsのURLから情報を抽出
        if (preg_match('/maps\/place\/([^\/]+)\/(@[^\/]+)/', $url, $matches)) {
            // デコードして店舗名と住所を分離
            $placeInfo = urldecode($matches[1]);
            $coordinates = str_replace('@', '', $matches[2]);

            // 座標情報を分解
            $coords = explode(',', $coordinates);
            if (count($coords) >= 2) {
                $this->latitude = (float) $coords[0];
                $this->longitude = (float) $coords[1];
            }

            // 店舗名と住所を分離（最後の "+" または " " で分割）
            $parts = preg_split('/\+|\s+/', $placeInfo, 2);
            if (count($parts) >= 2) {
                $this->name = str_replace('+', ' ', $parts[0]);
                $this->address = str_replace('+', ' ', $parts[1]);
            } else {
                $this->name = str_replace('+', ' ', $placeInfo);
            }

            // 電話番号の抽出（URLに含まれている場合）
            if (preg_match('/tel:([^\/\s]+)/', $url, $telMatches)) {
                $this->phone = $telMatches[1];
            }
        }
    } catch (\Exception $e) {
        // エラー処理
    } finally {
        $this->isProcessing = false;
        $this->googleMapsUrl = ''; // URLをクリア
    }
};

?>

<div class="page-container">
    <div class="page-content">
        <!-- ヘッダー -->
        <div class="page-header">
            <div class="page-header-content">
                <div>
                    <h1 class="page-title">新規訪問先の追加</h1>
                    <p class="page-subtitle">新しい訪問先の情報を入力してください</p>
                </div>
                <a href="{{ route('dashboard') }}" class="client-button-primary">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                    戻る
                </a>
            </div>
        </div>

        <!-- フォーム -->
        <div class="card">
            <form wire:submit="save" class="card-content">
                <!-- GoogleマップURL入力 -->
                <div class="border-b border-gray-200 dark:border-gray-700 pb-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Googleマップから情報を取得</h3>
                    <div class="space-y-3">
                        <label for="googleMapsUrl" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            GoogleマップのURL
                        </label>
                        <div class="flex space-x-3">
                            <input type="text" wire:model.live="googleMapsUrl" id="googleMapsUrl"
                                placeholder="GoogleマップのURLを貼り付け"
                                class="flex-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                            <button type="button" wire:click="extractFromGoogleMaps" wire:loading.attr="disabled"
                                wire:loading.class="opacity-50"
                                class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50">
                                <span wire:loading.remove>取得</span>
                                <span wire:loading>処理中...</span>
                            </button>
                        </div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            GoogleマップのURLを貼り付けると、会社名・住所・電話番号を自動入力します
                        </p>
                    </div>
                </div>

                <!-- 基本情報 -->
                <div class="space-y-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">基本情報</h3>

                    <!-- 会社名 -->
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            会社名 <span class="text-red-500">*</span>
                        </label>
                        <input type="text" wire:model.live="name" id="name"
                            class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                        @error('name')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- 住所 -->
                    <div>
                        <label for="address" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            住所 <span class="text-red-500">*</span>
                        </label>
                        <input type="text" wire:model.live="address" id="address"
                            class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                        @error('address')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- 連絡先情報 -->
                    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                        <div>
                            <label for="phone"
                                class="block text-sm font-medium text-gray-700 dark:text-gray-300">電話番号</label>
                            <input type="tel" wire:model.live="phone" id="phone"
                                class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                            @error('phone')
                                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="email"
                                class="block text-sm font-medium text-gray-700 dark:text-gray-300">メールアドレス</label>
                            <input type="email" wire:model.live="email" id="email"
                                class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                            @error('email')
                                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                <!-- 担当者情報 -->
                <div class="space-y-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">担当者情報</h3>

                    <div class="grid grid-cols-1 gap-6 sm:grid-cols-3">
                        <div>
                            <label for="contact_person"
                                class="block text-sm font-medium text-gray-700 dark:text-gray-300">担当者名</label>
                            <input type="text" wire:model.live="contact_person" id="contact_person"
                                class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                            @error('contact_person')
                                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="department"
                                class="block text-sm font-medium text-gray-700 dark:text-gray-300">部署</label>
                            <input type="text" wire:model.live="department" id="department"
                                class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                            @error('department')
                                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="position"
                                class="block text-sm font-medium text-gray-700 dark:text-gray-300">役職</label>
                            <input type="text" wire:model.live="position" id="position"
                                class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                            @error('position')
                                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                <!-- 備考 -->
                <div>
                    <label for="notes" class="block text-sm font-medium text-gray-700 dark:text-gray-300">備考</label>
                    <textarea wire:model.live="notes" id="notes" rows="4"
                        class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm"></textarea>
                    @error('notes')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <!-- ボタン -->
                <div class="flex justify-end space-x-3 pt-6 border-t border-gray-200 dark:border-gray-700">
                    <a href="{{ route('dashboard') }}"
                        class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        キャンセル
                    </a>
                    <button type="submit" wire:loading.attr="disabled" wire:loading.class="opacity-50"
                        class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50">
                        <span wire:loading.remove>保存</span>
                        <span wire:loading>保存中...</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
