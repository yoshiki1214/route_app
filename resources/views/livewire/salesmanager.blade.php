<?php

use function Livewire\Volt\{state};
use App\Models\Client;

state([
    'activeTab' => 'clients',
    'selectedClient' => null,
]);

$selectClient = function ($clientId) {
    $this->selectedClient = Client::find($clientId);
};

$refreshClients = function () {
    $this->dispatch('refresh-clients');
};

?>

<div class="min-h-screen bg-gray-100">
    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center">
                <h1 class="text-2xl font-semibold text-gray-900">営業管理システム</h1>
                <div class="flex space-x-4">
                    <button wire:click="$set('activeTab', 'clients')" @class([
                        'px-4 py-2 rounded',
                        'bg-blue-600 text-white' => $activeTab === 'clients',
                        'bg-gray-200 text-gray-700 hover:bg-gray-300' => $activeTab !== 'clients',
                    ])>
                        訪問先一覧
                    </button>
                    <!-- ルート作成はデスクトップのみ表示 -->
                    <button wire:click="$set('activeTab', 'route')" @class([
                        'px-4 py-2 rounded hidden lg:block',
                        'bg-blue-600 text-white' => $activeTab === 'route',
                        'bg-gray-200 text-gray-700 hover:bg-gray-300' => $activeTab !== 'route',
                    ])>
                        ルート作成
                    </button>
                </div>
            </div>

            <div class="mt-6">
                @if ($activeTab === 'clients')
                    <div class="mb-4 flex justify-end">
                        <livewire:client-create />
                    </div>
                    <div class="grid grid-cols-1 gap-6">
                        <div>
                            <livewire:clientlist />
                        </div>
                    </div>
                @elseif($activeTab === 'route')
                    <livewire:routemap />
                @endif
            </div>

            @if ($selectedClient)
                <div class="mt-6">
                    <livewire:clientmemo :client="$selectedClient" />
                </div>
            @endif
        </div>
    </div>
</div>
