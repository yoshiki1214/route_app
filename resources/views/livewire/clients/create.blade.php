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

// ★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★
// ★                                                                       ★
// ★  ここから下の `$formatAddressFromPrefecture` 関数を置き換えてください  ★
// ★                                                                       ★
// ★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★

$formatAddressFromPrefecture = function ($address) {
    if (empty($address)) {
        return '';
    }

    // 郵便番号を除去
    $address = preg_replace('/^〒\d{3}-\d{4}\s*/', '', $address);
    // 先頭や末尾の不要な空白、カンマを除去
    $address = trim($address, " \t\n\r\0\x0B,");

    // 日本の都道府県リスト
    $prefectures = ['北海道', '青森県', '岩手県', '宮城県', '秋田県', '山形県', '福島県', '茨城県', '栃木県', '群馬県', '埼玉県', '千葉県', '東京都', '神奈川県', '新潟県', '富山県', '石川県', '福井県', '山梨県', '長野県', '岐阜県', '静岡県', '愛知県', '三重県', '滋賀県', '京都府', '大阪府', '兵庫県', '奈良県', '和歌山県', '鳥取県', '島根県', '岡山県', '広島県', '山口県', '徳島県', '香川県', '愛媛県', '高知県', '福岡県', '佐賀県', '長崎県', '熊本県', '大分県', '宮崎県', '鹿児島県', '沖縄県'];

    $foundPrefecture = '';
    foreach ($prefectures as $prefecture) {
        // str_contains は PHP 8.0+
        if (str_contains($address, $prefecture)) {
            $foundPrefecture = $prefecture;
            break;
        }
    }

    // 都道府県が見つかった場合
    if ($foundPrefecture) {
        // 都道府県より前の部分を店舗名候補として分離
        $parts = explode($foundPrefecture, $address, 2);
        $potentialName = trim($parts[0]);
        $addressAfterPrefecture = trim($parts[1]);

        // 候補が空でなく、かつ数字で始まらない場合のみ店舗名として採用
        if (!empty($potentialName) && !preg_match('/^\d/', $potentialName)) {
            // 既存のnameが空の場合のみ設定
            if (empty($this->name)) {
                $this->name = $potentialName;
            }
        }

        // 整形後の住所は「都道府県 + それ以降」
        // これにより番地や建物名が消えるのを防ぐ
        return $foundPrefecture . $addressAfterPrefecture;
    }

    // 都道府県が見つからない場合は、元の住所をそのまま返す
    return $address;
};

// ★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★
// ★                                                                       ★
// ★  ここから上の `$formatAddressFromPrefecture` 関数を置き換えてください  ★
// ★                                                                       ★
// ★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★★

$expandShortUrl = function ($url) {
    try {
        $context = stream_context_create([
            'http' => [
                'method' => 'HEAD',
                'follow_location' => false,
                'timeout' => 10,
                'user_agent' => 'Mozilla/5.0 (compatible; Laravel App)',
            ],
        ]);

        $headers = get_headers($url, 1, $context);

        if ($headers && isset($headers['Location'])) {
            $location = is_array($headers['Location']) ? end($headers['Location']) : $headers['Location'];
            return $location;
        }

        return null;
    } catch (\Exception $e) {
        return null;
    }
};

$extractFromGoogleMaps = function () {
    $this->isProcessing = true;

    try {
        $url = $this->googleMapsUrl;

        if (empty($url)) {
            session()->flash('error', 'URLが入力されていません。');
            return;
        }

        // URLの正規化
        $url = trim($url);

        // 短縮URLの展開を試行
        if (strpos($url, 'goo.gl') !== false || strpos($url, 'bit.ly') !== false || strpos($url, 'maps.app.goo.gl') !== false) {
            $expandedUrl = $this->expandShortUrl($url);
            if ($expandedUrl) {
                $url = $expandedUrl;
                session()->flash('info', '短縮URLを展開しました: ' . substr($url, 0, 100) . '...');
            } else {
                session()->flash('warning', '短縮URLの展開に失敗しました。元のURLで処理を続行します。');
            }
        }

        $extracted = false;

        // デバッグ: 解析対象のURLをログ出力
        \Log::info('Google Maps URL解析開始: ' . $url);

        // 1. 新しいGoogleマップURL形式 (maps/place/...) の解析
        if (preg_match('/maps\/place\/([^\/\?]+)(?:\/(@[^\/\?]+))?/', $url, $matches)) {
            $placeInfo = urldecode($matches[1]);

            // 座標情報がある場合
            if (isset($matches[2])) {
                $coordinates = str_replace('@', '', $matches[2]);
                $coords = explode(',', $coordinates);
                if (count($coords) >= 2) {
                    $this->latitude = (float) $coords[0];
                    $this->longitude = (float) $coords[1];
                }
            }

            // 店舗名と住所を分離
            $parts = preg_split('/\+/', $placeInfo, 2);
            if (count($parts) >= 2) {
                $potentialName = str_replace('+', ' ', $parts[0]);
                $potentialAddress = str_replace('+', ' ', $parts[1]);

                // 最初の部分が数字で始まる場合は住所、そうでなければ店舗名
                if (preg_match('/^\d/', $potentialName)) {
                    // 最初の部分が住所の場合
                    $this->name = '';
                    $this->address = $this->formatAddressFromPrefecture($potentialName . ', ' . $potentialAddress);
                } else {
                    // 最初の部分が店舗名の場合
                    $this->name = $potentialName;
                    $this->address = $this->formatAddressFromPrefecture($potentialAddress);
                }
            } else {
                $singlePart = str_replace('+', ' ', $placeInfo);
                // 数字で始まる場合は住所、そうでなければ店舗名
                if (preg_match('/^\d/', $singlePart)) {
                    $this->name = '';
                    $this->address = $this->formatAddressFromPrefecture($singlePart);
                } else {
                    $this->name = $singlePart;
                    $this->address = '';
                }
            }
            \Log::info('maps/place形式で解析成功: name=' . $this->name . ', address=' . $this->address);
            $extracted = true;
        }
        // 2. 検索形式 (maps/search/...)
        elseif (preg_match('/maps\/search\/([^\/\?]+)(?:\/(@[^\/\?]+))?/', $url, $matches)) {
            $searchTerm = urldecode($matches[1]);

            // 座標情報がある場合
            if (isset($matches[2])) {
                $coordinates = str_replace('@', '', $matches[2]);
                $coords = explode(',', $coordinates);
                if (count($coords) >= 2) {
                    $this->latitude = (float) $coords[0];
                    $this->longitude = (float) $coords[1];
                }
            }

            $this->address = $this->formatAddressFromPrefecture(str_replace('+', ' ', $searchTerm));
            \Log::info('maps/search形式で解析成功: address=' . $this->address);
            $extracted = true;
        }
        // 3. 座標のみのURL形式 (@lat,lng,zoom)
        elseif (preg_match('/@([0-9.-]+),([0-9.-]+),([0-9.]+)z/', $url, $matches)) {
            $this->latitude = (float) $matches[1];
            $this->longitude = (float) $matches[2];
            $extracted = true;
        }
        // 4. 座標のみのURL形式 (@lat,lng)
        elseif (preg_match('/@([0-9.-]+),([0-9.-]+)/', $url, $matches)) {
            $this->latitude = (float) $matches[1];
            $this->longitude = (float) $matches[2];
            $extracted = true;
        }
        // 5. 住所検索形式 (maps/search/...)
        elseif (preg_match('/maps\/search\/([^\/\?]+)/', $url, $matches)) {
            $searchTerm = urldecode($matches[1]);
            $this->address = str_replace('+', ' ', $searchTerm);
            $extracted = true;
        }
        // 6. 一般的な検索クエリ形式
        elseif (preg_match('/[?&]q=([^&]+)/', $url, $matches)) {
            $searchTerm = urldecode($matches[1]);
            $this->address = $this->formatAddressFromPrefecture(str_replace('+', ' ', $searchTerm));
            $extracted = true;
        }
        // 7. maps.app.goo.gl形式の解析
        elseif (preg_match('/maps\.app\.goo\.gl\/[a-zA-Z0-9]+/', $url)) {
            // 短縮URLが展開されていない場合は、再度展開を試行
            if (strpos($url, 'maps.app.goo.gl') !== false) {
                $expandedUrl = $this->expandShortUrl($url);
                if ($expandedUrl) {
                    $url = $expandedUrl;
                    // 展開されたURLで再帰的に解析
                    return $this->extractFromGoogleMaps();
                }
            }
            $extracted = true;
        }

        // 電話番号の抽出
        if (preg_match('/tel:([^\/\s&]+)/', $url, $telMatches)) {
            $this->phone = $telMatches[1];
        }

        if (!$extracted) {
            session()->flash('error', 'GoogleマップURLの形式が認識できませんでした。');
            return;
        }

        // 座標が取得できた場合、Google Maps APIで詳細情報を取得
        if ($this->latitude && $this->longitude) {
            $this->dispatch('get-place-details-from-coords', lat: $this->latitude, lng: $this->longitude);
        }
        // 住所が設定されているが緯度経度が設定されていない場合、住所から取得を試行
        elseif ($this->address && (!$this->latitude || !$this->longitude)) {
            $this->dispatch('get-lat-lng', address: $this->address);
        }

        session()->flash('success', 'GoogleマップURLから情報を取得しました。');
    } catch (\Exception $e) {
        session()->flash('error', 'GoogleマップURLの解析に失敗しました: ' . $e->getMessage());
    } finally {
        $this->isProcessing = false;
        $this->googleMapsUrl = ''; // URLをクリア
    }
};

$setLatLng = function ($lat, $lng, $formattedAddress = null) {
    $this->latitude = $lat;
    $this->longitude = $lng;
    if ($formattedAddress && !$this->address) {
        $this->address = $formattedAddress;
    }
};

$setPlaceDetails = function ($name, $address, $phone, $lat, $lng, $website = null) {
    if ($name) {
        $this->name = $name;
    }
    if ($address) {
        $this->address = $address;
    }
    if ($phone) {
        $this->phone = $phone;
    }
    if ($lat) {
        $this->latitude = $lat;
    }
    if ($lng) {
        $this->longitude = $lng;
    }
    if ($website) {
        $this->website = $website;
    }
};

?>

<div class="page-container bg-gray-50 dark:bg-gray-900 min-h-screen">
    <div class="page-content max-w-5xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
        <!-- ヘッダー -->
        <div class="page-header bg-white dark:bg-gray-800 shadow-sm rounded-lg mb-8 p-6">
            <div class="page-header-content flex justify-between items-center">
                <div>
                    <h1 class="page-title text-3xl font-bold text-gray-900 dark:text-white">新規訪問先の追加</h1>
                    <p class="page-subtitle mt-2 text-gray-600 dark:text-gray-300">新しい訪問先の情報を入力してください</p>
                </div>
                <a href="{{ route('dashboard') }}"
                    class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md font-semibold text-gray-700 dark:text-gray-200 shadow-sm hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-all duration-200">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                    戻る
                </a>
            </div>
        </div>

        <!-- フォーム -->
        <div class="bg-white dark:bg-gray-800 shadow-lg rounded-lg overflow-hidden">
            <form wire:submit="save" class="divide-y divide-gray-200 dark:divide-gray-700">
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
                                class="flex-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200 sm:text-sm hover:border-blue-300 dark:hover:border-blue-400">
                            <button type="button" wire:click="extractFromGoogleMaps" wire:loading.attr="disabled"
                                wire:loading.class="opacity-50"
                                class="inline-flex items-center px-6 py-2 border border-transparent text-sm font-semibold rounded-lg shadow-sm text-white bg-blue-600 hover:bg-blue-700 transform hover:scale-105 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 transition-all duration-200">
                                <span wire:loading.remove>反映</span>
                                <span wire:loading>処理中...</span>
                            </button>
                        </div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            GoogleマップのURLを貼り付けて「反映」ボタンをクリックすると、会社名・住所・電話番号を自動入力します
                        </p>
                    </div>
                </div>

                <!-- 基本情報 -->
                <div class="space-y-8 p-6">
                    <div class="border-b border-gray-200 dark:border-gray-700 pb-4">
                        <h3 class="text-xl font-bold text-gray-900 dark:text-white flex items-center">
                            <svg class="w-6 h-6 mr-2 text-blue-500" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                            </svg>
                            基本情報
                        </h3>
                    </div>

                    <!-- 会社名 -->
                    <div class="relative">
                        <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            会社名 <span class="text-red-500">*</span>
                        </label>
                        <input type="text" wire:model.blur="name" id="name"
                            class="block w-full px-4 py-3 border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200 sm:text-sm hover:border-blue-300 dark:hover:border-blue-400">
                        @error('name')
                            <p class="mt-2 text-sm text-red-600 flex items-center">
                                <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd"
                                        d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z"
                                        clip-rule="evenodd" />
                                </svg>
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    <!-- 住所 -->
                    <div class="relative">
                        <label for="address" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            住所 <span class="text-red-500">*</span>
                        </label>
                        <input type="text" wire:model.blur="address" id="address"
                            class="block w-full px-4 py-3 border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200 sm:text-sm hover:border-blue-300 dark:hover:border-blue-400">
                        @error('address')
                            <p class="mt-2 text-sm text-red-600 flex items-center">
                                <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd"
                                        d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z"
                                        clip-rule="evenodd" />
                                </svg>
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    <!-- 連絡先情報 -->
                    <div class="grid grid-cols-1 gap-8 sm:grid-cols-2">
                        <div class="relative">
                            <label for="phone"
                                class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2 flex items-center">
                                <svg class="w-4 h-4 mr-2 text-gray-500" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                </svg>
                                電話番号
                            </label>
                            <input type="tel" wire:model.blur="phone" id="phone"
                                class="block w-full px-4 py-3 border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200 sm:text-sm hover:border-blue-300 dark:hover:border-blue-400">
                            @error('phone')
                                <p class="mt-2 text-sm text-red-600 flex items-center">
                                    <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd"
                                            d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z"
                                            clip-rule="evenodd" />
                                    </svg>
                                    {{ $message }}
                                </p>
                            @enderror
                        </div>

                        <div class="relative">
                            <label for="email"
                                class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2 flex items-center">
                                <svg class="w-4 h-4 mr-2 text-gray-500" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                </svg>
                                メールアドレス
                            </label>
                            <input type="email" wire:model.blur="email" id="email"
                                class="block w-full px-4 py-3 border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200 sm:text-sm hover:border-blue-300 dark:hover:border-blue-400">
                            @error('email')
                                <p class="mt-2 text-sm text-red-600 flex items-center">
                                    <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd"
                                            d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z"
                                            clip-rule="evenodd" />
                                    </svg>
                                    {{ $message }}
                                </p>
                            @enderror
                        </div>
                    </div>
                </div>

                <!-- 担当者情報 -->
                <div class="space-y-8 p-6 bg-gray-50 dark:bg-gray-800/50">
                    <div class="border-b border-gray-200 dark:border-gray-700 pb-4">
                        <h3 class="text-xl font-bold text-gray-900 dark:text-white flex items-center">
                            <svg class="w-6 h-6 mr-2 text-blue-500" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                            担当者情報
                        </h3>
                    </div>

                    <div class="grid grid-cols-1 gap-8 sm:grid-cols-3">
                        <div class="relative">
                            <label for="contact_person"
                                class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2 flex items-center">
                                <svg class="w-4 h-4 mr-2 text-gray-500" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                                担当者名
                            </label>
                            <input type="text" wire:model.blur="contact_person" id="contact_person"
                                class="block w-full px-4 py-3 border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200 sm:text-sm hover:border-blue-300 dark:hover:border-blue-400">
                            @error('contact_person')
                                <p class="mt-2 text-sm text-red-600 flex items-center">
                                    <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd"
                                            d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z"
                                            clip-rule="evenodd" />
                                    </svg>
                                    {{ $message }}
                                </p>
                            @enderror
                        </div>

                        <div class="relative">
                            <label for="department"
                                class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2 flex items-center">
                                <svg class="w-4 h-4 mr-2 text-gray-500" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                </svg>
                                部署
                            </label>
                            <input type="text" wire:model.blur="department" id="department"
                                class="block w-full px-4 py-3 border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200 sm:text-sm hover:border-blue-300 dark:hover:border-blue-400">
                            @error('department')
                                <p class="mt-2 text-sm text-red-600 flex items-center">
                                    <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd"
                                            d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z"
                                            clip-rule="evenodd" />
                                    </svg>
                                    {{ $message }}
                                </p>
                            @enderror
                        </div>

                        <div class="relative">
                            <label for="position"
                                class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2 flex items-center">
                                <svg class="w-4 h-4 mr-2 text-gray-500" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                </svg>
                                役職
                            </label>
                            <input type="text" wire:model.blur="position" id="position"
                                class="block w-full px-4 py-3 border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200 sm:text-sm hover:border-blue-300 dark:hover:border-blue-400">
                            @error('position')
                                <p class="mt-2 text-sm text-red-600 flex items-center">
                                    <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd"
                                            d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z"
                                            clip-rule="evenodd" />
                                    </svg>
                                    {{ $message }}
                                </p>
                            @enderror
                        </div>
                    </div>
                </div>

                <!-- 備考 -->
                <div class="space-y-8 p-6">
                    <div class="relative">
                        <label for="notes"
                            class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2 flex items-center">
                            <svg class="w-4 h-4 mr-2 text-gray-500" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                            備考
                        </label>
                        <textarea wire:model.blur="notes" id="notes" rows="4"
                            class="block w-full px-4 py-3 border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200 sm:text-sm hover:border-blue-300 dark:hover:border-blue-400 resize-y"></textarea>
                        @error('notes')
                            <p class="mt-2 text-sm text-red-600 flex items-center">
                                <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd"
                                        d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z"
                                        clip-rule="evenodd" />
                                </svg>
                                {{ $message }}
                            </p>
                        @enderror
                    </div>
                </div>

                <!-- ボタン -->
                <div
                    class="flex justify-end space-x-4 p-6 bg-gray-50 dark:bg-gray-800/50 border-t border-gray-200 dark:border-gray-700">
                    <a href="{{ route('dashboard') }}"
                        class="inline-flex items-center px-6 py-3 border border-gray-300 dark:border-gray-600 rounded-lg shadow-sm text-sm font-semibold text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 transform hover:scale-105 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all duration-200">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 18L18 6M6 6l12 12" />
                        </svg>
                        キャンセル
                    </a>
                    <button type="submit" wire:loading.attr="disabled" wire:loading.class="opacity-50"
                        class="inline-flex items-center px-6 py-3 border border-transparent text-sm font-semibold rounded-lg shadow-sm text-white bg-blue-600 hover:bg-blue-700 transform hover:scale-105 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 transition-all duration-200">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M5 13l4 4L19 7" />
                        </svg>
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

        // 住所から緯度経度を取得
        Livewire.on('get-lat-lng', function(data) {
            if (window.getLatLngFromAddress) {
                window.getLatLngFromAddress(data.address, function(result) {
                    if (result) {
                        Livewire.dispatch('set-lat-lng', {
                            lat: result.lat,
                            lng: result.lng,
                            formattedAddress: result.formatted_address
                        });
                    }
                });
            }
        });

        // 座標からPlace IDを取得し、詳細情報を取得
        Livewire.on('get-place-details-from-coords', function(data) {
            if (window.getPlaceIdFromCoordinates) {
                window.getPlaceIdFromCoordinates(data.lat, data.lng, function(placeResult) {
                    if (placeResult) {
                        // フォールバック: Geocodingの結果を直接使用
                        if (placeResult.use_geocoding_result) {
                            console.log('Using Geocoding result as fallback');
                            Livewire.dispatch('set-place-details', {
                                name: placeResult.name,
                                address: placeResult.formatted_address,
                                phone: '', // Geocodingでは電話番号は取得できない
                                lat: placeResult.lat,
                                lng: placeResult.lng,
                                website: ''
                            });
                        } else if (placeResult.place_id && window.getPlaceDetails) {
                            // 新しいPlaces APIを使用
                            window.getPlaceDetails(placeResult.place_id, function(details) {
                                if (details) {
                                    // 住所コンポーネントから詳細な住所を構築
                                    let detailedAddress = '';
                                    if (details.address_components) {
                                        // 都道府県
                                        const prefecture = details.address_components
                                            .find(c =>
                                                c.types.includes(
                                                    'administrative_area_level_1'))
                                            ?.long_name || '';
                                        // 市区町村
                                        const city = details.address_components.find(
                                                c =>
                                                c.types.includes('locality') ||
                                                c.types.includes(
                                                    'administrative_area_level_2'))
                                            ?.long_name || '';
                                        // 町名
                                        const sublocality = details.address_components
                                            .find(c =>
                                                c.types.includes(
                                                    'sublocality_level_1') ||
                                                c.types.includes('sublocality'))
                                            ?.long_name || '';
                                        // 番地
                                        const streetNumber = details.address_components
                                            .find(c =>
                                                c.types.includes('premise') ||
                                                c.types.includes('street_number'))
                                            ?.long_name || '';
                                        // 建物名
                                        const building = details.address_components
                                            .find(c =>
                                                c.types.includes('establishment'))
                                            ?.long_name || '';

                                        // 住所を組み立て
                                        detailedAddress = [prefecture, city,
                                                sublocality, streetNumber, building
                                            ]
                                            .filter(part => part)
                                            .join(' ');
                                    }

                                    // フォールバックとして formatted_address を使用
                                    const finalAddress = detailedAddress || details
                                        .formatted_address || details.address;

                                    Livewire.dispatch('set-place-details', {
                                        name: details.name,
                                        address: finalAddress,
                                        phone: details.phone,
                                        lat: details.lat,
                                        lng: details.lng,
                                        website: details.website
                                    });

                                    console.log('Address components:', details
                                        .address_components);
                                    console.log('Built address:', detailedAddress);
                                    console.log('Final address:', finalAddress);
                                }
                            });
                        }
                    }
                });
            }
        });

        // 緯度経度を設定
        Livewire.on('set-lat-lng', function(data) {
            // Livewireのプロパティを直接更新
            @this.set('latitude', data.lat);
            @this.set('longitude', data.lng);
            if (data.formattedAddress && !@this.address) {
                @this.set('address', data.formattedAddress);
            }
        });

        // 詳細情報を設定
        Livewire.on('set-place-details', function(data) {
            if (data.name) @this.set('name', data.name);
            if (data.address) @this.set('address', data.address);
            if (data.phone) @this.set('phone', data.phone);
            if (data.lat) @this.set('latitude', data.lat);
            if (data.lng) @this.set('longitude', data.lng);
            if (data.website) @this.set('website', data.website);
        });
    });
</script>
