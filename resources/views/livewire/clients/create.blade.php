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

    // 緯度経度が設定されていない場合は、住所から取得
    if (!$this->latitude || !$this->longitude) {
        $validated['latitude'] = null;
        $validated['longitude'] = null;
    } else {
        $validated['latitude'] = $this->latitude;
        $validated['longitude'] = $this->longitude;
    }

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

        if (empty($url)) {
            return;
        }

        // 新しいGoogleマップURL形式 (maps/place/...) の解析
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
        }
        // 新しいGoogleマップURL形式 (search/...) の解析
        elseif (preg_match('/maps\/search\/([^\/]+)\/(@[^\/]+)/', $url, $matches)) {
            $placeInfo = urldecode($matches[1]);
            $coordinates = str_replace('@', '', $matches[2]);

            // 座標情報を分解
            $coords = explode(',', $coordinates);
            if (count($coords) >= 2) {
                $this->latitude = (float) $coords[0];
                $this->longitude = (float) $coords[1];
            }

            $this->name = str_replace('+', ' ', $placeInfo);
        }
        // 座標のみのURL形式 (@lat,lng,zoom)
        elseif (preg_match('/@([0-9.-]+),([0-9.-]+),([0-9.]+)z/', $url, $matches)) {
            $this->latitude = (float) $matches[1];
            $this->longitude = (float) $matches[2];
        }
        // 住所検索形式
        elseif (preg_match('/maps\/search\/([^\/\?]+)/', $url, $matches)) {
            $searchTerm = urldecode($matches[1]);
            $this->address = str_replace('+', ' ', $searchTerm);
        }

        // 電話番号の抽出（URLに含まれている場合）
        if (preg_match('/tel:([^\/\s]+)/', $url, $telMatches)) {
            $this->phone = $telMatches[1];
        }

        // 住所が設定されているが緯度経度が設定されていない場合、住所から取得を試行
        if ($this->address && (!$this->latitude || !$this->longitude)) {
            $this->dispatch('get-lat-lng', address: $this->address);
        }
    } catch (\Exception $e) {
        // エラー処理
        session()->flash('error', 'GoogleマップURLの解析に失敗しました: ' . $e->getMessage());
    } finally {
        $this->isProcessing = false;
        $this->googleMapsUrl = ''; // URLをクリア
    }
};

$getLatLngFromAddress = function () {
    if (!$this->address) {
        return;
    }

    $this->dispatch('get-lat-lng', address: $this->address);
};

$getPlaceDetailsFromUrl = function () {
    if (!$this->googleMapsUrl) {
        return;
    }

    $this->dispatch('get-place-details', url: $this->googleMapsUrl);
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
                            <input type="text" wire:model.blur="googleMapsUrl" id="googleMapsUrl"
                                placeholder="GoogleマップのURLを貼り付け"
                                class="flex-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                            <button type="button" wire:click="extractFromGoogleMaps" wire:loading.attr="disabled"
                                wire:loading.class="opacity-50"
                                class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50">
                                <span wire:loading.remove>基本取得</span>
                                <span wire:loading>処理中...</span>
                            </button>
                            <button type="button" wire:click="getPlaceDetailsFromUrl" wire:loading.attr="disabled"
                                wire:loading.class="opacity-50"
                                class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md shadow-sm text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50">
                                <span wire:loading.remove>詳細取得</span>
                                <span wire:loading>処理中...</span>
                            </button>
                        </div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            GoogleマップのURLを貼り付けて、「基本取得」でURL解析、「詳細取得」でGoogleマップAPIを使用して詳細情報を取得します
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
                        <input type="text" wire:model.blur="name" id="name"
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
                        <input type="text" wire:model.blur="address" id="address"
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
                            <input type="tel" wire:model.blur="phone" id="phone"
                                class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                            @error('phone')
                                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="email"
                                class="block text-sm font-medium text-gray-700 dark:text-gray-300">メールアドレス</label>
                            <input type="email" wire:model.blur="email" id="email"
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
                            <input type="text" wire:model.blur="contact_person" id="contact_person"
                                class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                            @error('contact_person')
                                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="department"
                                class="block text-sm font-medium text-gray-700 dark:text-gray-300">部署</label>
                            <input type="text" wire:model.blur="department" id="department"
                                class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                            @error('department')
                                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="position"
                                class="block text-sm font-medium text-gray-700 dark:text-gray-300">役職</label>
                            <input type="text" wire:model.blur="position" id="position"
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
                    <textarea wire:model.blur="notes" id="notes" rows="4"
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

<script>
    document.addEventListener('livewire:init', function() {
        // スクロール位置保持機能は共通のscrollPositionManagerを使用

        // Google Maps APIが読み込まれた時の処理
        window.addEventListener('googleMapsReady', function() {
            console.log('Google Maps ready in client create');
        });

        // GoogleマップURLから詳細情報を取得するイベントリスナー
        Livewire.on('get-place-details', function(data) {
            const url = data.url;
            if (url && window.googleMapsReady) {
                extractPlaceDetailsFromUrl(url);
            }
        });

        // URLからPlace IDを抽出して詳細情報を取得する関数
        function extractPlaceDetailsFromUrl(url) {
            try {
                // Place IDを抽出
                let placeId = null;

                // 新しいURL形式からPlace IDを抽出
                if (url.includes('/place/')) {
                    const match = url.match(/\/place\/([^\/]+)\//);
                    if (match) {
                        placeId = match[1];
                    }
                }

                if (placeId) {
                    // Places APIを使用して詳細情報を取得
                    const service = new google.maps.places.PlacesService(document.createElement('div'));
                    service.getDetails({
                        placeId: placeId,
                        fields: ['name', 'formatted_address', 'formatted_phone_number', 'geometry',
                            'website'
                        ]
                    }, function(place, status) {
                        if (status === google.maps.places.PlacesServiceStatus.OK) {
                            // 取得した情報をLivewireに送信
                            Livewire.dispatch('set-place-details', {
                                name: place.name || '',
                                address: place.formatted_address || '',
                                phone: place.formatted_phone_number || '',
                                lat: place.geometry.location.lat(),
                                lng: place.geometry.location.lng(),
                                website: place.website || ''
                            });
                        } else {
                            console.error('Place details request failed:', status);
                            // フォールバック: URL解析を試行
                            @this.call('extractFromGoogleMaps');
                        }
                    });
                } else {
                    // Place IDが見つからない場合はURL解析を試行
                    @this.call('extractFromGoogleMaps');
                }
            } catch (error) {
                console.error('Error extracting place details:', error);
                // エラー時はURL解析を試行
                @this.call('extractFromGoogleMaps');
            }
        }

        // 詳細情報が設定された時のイベントリスナー
        Livewire.on('set-place-details', function(data) {
            @this.set('name', data.name);
            @this.set('address', data.address);
            @this.set('phone', data.phone);
            @this.set('latitude', data.lat);
            @this.set('longitude', data.lng);
            if (data.website) {
                @this.set('email', data.website);
            }
        });
    });
</script>
