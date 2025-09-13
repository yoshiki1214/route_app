<?php

use function Livewire\Volt\{state, mount, updated};

state([
    'defaultDuration' => 60, // デフォルト60分
]);

mount(function () {
    // ユーザーの設定を取得（セッションまたはデータベースから）
    $this->defaultDuration = session('appointment_default_duration', 60);
});

$saveSettings = function () {
    // セッションに保存
    session(['appointment_default_duration' => $this->defaultDuration]);

    // 成功メッセージを表示
    session()->flash('settings-saved', '設定が保存されました。');
};

?>

<section class="w-full">
    @include('partials.settings-heading')

    <x-settings.layout :heading="__('Application Settings')" :subheading="__('アプリケーションの動作設定を管理します')">
        @if (session('settings-saved'))
            <div
                class="mb-4 p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg">
                <p class="text-sm text-green-800 dark:text-green-200">
                    {{ session('settings-saved') }}
                </p>
            </div>
        @endif

        <form wire:submit="saveSettings" class="space-y-6">
            <div>
                <flux:label for="defaultDuration">{{ __('Default Appointment Duration') }}</flux:label>
                <flux:select wire:model="defaultDuration" id="defaultDuration">
                    <option value="15">15分</option>
                    <option value="30">30分</option>
                    <option value="45">45分</option>
                    <option value="60">60分</option>
                    <option value="90">90分</option>
                    <option value="120">120分</option>
                    <option value="150">150分</option>
                    <option value="180">180分</option>
                </flux:select>
                <flux:description>
                    アポイントメント作成時に開始時間を入力すると、この時間が自動で終了時間として設定されます。
                </flux:description>
            </div>

            <div class="flex items-center justify-end">
                <flux:button type="submit" variant="primary">
                    {{ __('Save Settings') }}
                </flux:button>
            </div>
        </form>
    </x-settings.layout>
</section>
