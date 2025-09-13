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
    'showModal' => false,
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

    Client::create($validated);

    $this->reset();
    $this->showModal = false;
    $this->dispatch('client-created');
};

$openModal = function () {
    $this->showModal = true;
};

$closeModal = function () {
    $this->reset();
    $this->showModal = false;
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

<div>
    <!-- 訪問先追加ボタン（URL遷移） -->
    <a href="{{ route('clients.create') }}" class="client-button-primary">
        <svg class="client-button-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
        </svg>
        訪問先を追加
    </a>

    <!-- モーダル -->
    @if ($showModal)
        <div x-data="{ show: @entangle('showModal') }" x-show="show" x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0" class="client-modal-overlay" @click.self="show = false">
            <div class="client-modal-container">
                <div class="client-modal-backdrop" aria-hidden="true">
                    <div class="client-modal-backdrop-bg"></div>
                </div>

                <div class="client-modal-dialog" x-transition:enter="transition ease-out duration-300"
                    x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                    x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                    x-transition:leave="transition ease-in duration-200"
                    x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                    x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">
                    <form wire:submit="save">
                        <div class="client-modal-header">
                            <h3 class="client-modal-title">新規訪問先の追加</h3>

                            <!-- GoogleマップURL入力 -->
                            <div class="client-google-maps-section">
                                <label class="client-google-maps-label">
                                    GoogleマップのURLから情報を取得
                                </label>
                                <div class="client-google-maps-input-container">
                                    <input type="text" wire:model="googleMapsUrl" placeholder="GoogleマップのURLを貼り付け"
                                        class="client-google-maps-input">
                                    <button type="button" wire:click="extractFromGoogleMaps"
                                        class="client-google-maps-button" wire:loading.attr="disabled"
                                        wire:loading.class="client-google-maps-button-disabled">
                                        <span wire:loading.remove>取得</span>
                                        <span wire:loading>処理中...</span>
                                    </button>
                                </div>
                                <p class="client-google-maps-help">
                                    GoogleマップのURLを貼り付けると、会社名・住所・電話番号を自動入力します
                                </p>
                            </div>

                            <div class="client-form-group">
                                <!-- 会社名 -->
                                <div>
                                    <label for="name" class="client-form-field">会社名 <span
                                            class="client-form-field-required">*</span></label>
                                    <input type="text" wire:model="name" id="name" class="client-form-input">
                                    @error('name')
                                        <span class="client-form-error">{{ $message }}</span>
                                    @enderror
                                </div>

                                <!-- 住所 -->
                                <div>
                                    <label for="address" class="client-form-field">住所 <span
                                            class="client-form-field-required">*</span></label>
                                    <input type="text" wire:model="address" id="address" class="client-form-input">
                                    @error('address')
                                        <span class="client-form-error">{{ $message }}</span>
                                    @enderror
                                </div>

                                <!-- 連絡先情報 -->
                                <div class="client-form-grid">
                                    <div>
                                        <label for="phone" class="client-form-field">電話番号</label>
                                        <input type="tel" wire:model="phone" id="phone"
                                            class="client-form-input">
                                        @error('phone')
                                            <span class="client-form-error">{{ $message }}</span>
                                        @enderror
                                    </div>

                                    <div>
                                        <label for="email" class="client-form-field">メールアドレス</label>
                                        <input type="email" wire:model="email" id="email"
                                            class="client-form-input">
                                        @error('email')
                                            <span class="client-form-error">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>

                                <!-- 担当者情報 -->
                                <div class="client-form-grid-3">
                                    <div class="client-form-grid-span-1">
                                        <label for="contact_person" class="client-form-field">担当者名</label>
                                        <input type="text" wire:model="contact_person" id="contact_person"
                                            class="client-form-input">
                                        @error('contact_person')
                                            <span class="client-form-error">{{ $message }}</span>
                                        @enderror
                                    </div>

                                    <div>
                                        <label for="department" class="client-form-field">部署</label>
                                        <input type="text" wire:model="department" id="department"
                                            class="client-form-input">
                                        @error('department')
                                            <span class="client-form-error">{{ $message }}</span>
                                        @enderror
                                    </div>

                                    <div>
                                        <label for="position" class="client-form-field">役職</label>
                                        <input type="text" wire:model="position" id="position"
                                            class="client-form-input">
                                        @error('position')
                                            <span class="client-form-error">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>

                                <!-- 備考 -->
                                <div>
                                    <label for="notes" class="client-form-field">備考</label>
                                    <textarea wire:model="notes" id="notes" rows="3" class="client-form-textarea"></textarea>
                                    @error('notes')
                                        <span class="client-form-error">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="client-modal-footer">
                            <button type="submit" class="client-modal-button-primary">
                                保存
                            </button>
                            <button type="button" wire:click="closeModal" class="client-modal-button-secondary">
                                キャンセル
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>
