<?php

use function Livewire\Volt\{state, rules, mount};
use App\Models\User;

state([
    'travel_mode' => 'driving',
    'use_toll_roads' => true,
    'use_highways' => true,
    'status' => '',
]);

mount(function () {
    $user = auth()->user();
    $this->travel_mode = $user->travel_mode;
    $this->use_toll_roads = $user->use_toll_roads;
    $this->use_highways = $user->use_highways;
});

$updateSettings = function () {
    $user = auth()->user();
    $user->update([
        'travel_mode' => $this->travel_mode,
        'use_toll_roads' => $this->use_toll_roads,
        'use_highways' => $this->use_highways,
    ]);

    $this->status = '設定を保存しました';
    $this->dispatch('settings-updated');
};

?>

<x-settings.layout>
    <x-slot:heading>
        {{ __('ルート設定') }}
    </x-slot>
    <x-slot:subheading>
        {{ __('ルート表示に関する設定を管理します。') }}
    </x-slot>

    <div class="space-y-6">
        <form wire:submit="updateSettings" class="space-y-6">
            <!-- 移動手段の選択 -->
            <div>
                <x-input-label for="travel_mode" :value="__('移動手段')" />
                <select id="travel_mode" wire:model="travel_mode"
                    class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 shadow-sm">
                    <option value="driving">{{ __('車') }}</option>
                    <option value="walking">{{ __('徒歩') }}</option>
                    <option value="transit">{{ __('公共交通機関') }}</option>
                </select>
                <x-input-error :messages="$errors->get('travel_mode')" class="mt-2" />
            </div>

            <!-- 車の場合の追加設定 -->
            @if ($travel_mode === 'driving')
                <div class="space-y-4">
                    <div class="flex items-center">
                        <input type="checkbox" wire:model="use_toll_roads" id="use_toll_roads"
                            class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:focus:ring-offset-gray-800">
                        <label for="use_toll_roads" class="ml-2 text-sm text-gray-600 dark:text-gray-400">
                            {{ __('有料道路を使用する') }}
                        </label>
                    </div>

                    <div class="flex items-center">
                        <input type="checkbox" wire:model="use_highways" id="use_highways"
                            class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:focus:ring-offset-gray-800">
                        <label for="use_highways" class="ml-2 text-sm text-gray-600 dark:text-gray-400">
                            {{ __('高速道路を使用する') }}
                        </label>
                    </div>
                </div>
            @endif

            <div class="flex items-center gap-4">
                <x-primary-button>{{ __('保存') }}</x-primary-button>

                @if ($status)
                    <p x-data="{ show: true }" x-show="show" x-transition x-init="setTimeout(() => show = false, 2000)"
                        class="text-sm text-gray-600 dark:text-gray-400">
                        {{ $status }}
                    </p>
                @endif
            </div>
        </form>
    </div>
</x-settings.layout>
