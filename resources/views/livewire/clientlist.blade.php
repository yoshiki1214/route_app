<?php

use function Livewire\Volt\{state};
use App\Models\Client;
use Carbon\Carbon;

state([
    'clients' => fn() => Client::with([
        'visits' => function ($query) {
            $query->latest('visited_at');
        },
    ])->get(),
    'search' => '',
    'sortField' => 'name',
    'sortDirection' => 'asc',
]);

$sortBy = function ($field) {
    if ($this->sortField === $field) {
        $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
    } else {
        $this->sortField = $field;
        $this->sortDirection = 'asc';
    }

    $this->clients = $this->clients->sortBy($field, SORT_REGULAR, $this->sortDirection === 'desc');
};

$getLastVisitDays = function ($client) {
    $lastVisit = $client->visits->first();
    if (!$lastVisit) {
        return null;
    }
    return Carbon::parse($lastVisit->visited_at)->diffInDays(now());
};

?>

<div class="p-4">
    <div class="mb-4">
        <input type="text" wire:model.live="search" placeholder="訪問先を検索..." class="w-full px-4 py-2 border rounded-lg">
    </div>

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th wire:click="sortBy('name')"
                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer">
                        会社名
                        @if ($sortField === 'name')
                            <span class="ml-1">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span>
                        @endif
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        最終訪問
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        連絡先
                    </th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                        アクション
                    </th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @foreach ($clients->filter(function ($client) {
        return str_contains(strtolower($client->name), strtolower($search));
    }) as $client)
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm font-medium text-gray-900">{{ $client->name }}</div>
                            <div class="text-sm text-gray-500">{{ $client->address }}</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            @php
                                $days = $this->getLastVisitDays($client);
                            @endphp
                            <div @class([
                                'text-sm',
                                'text-red-600' => $days && $days > 30,
                                'text-yellow-600' => $days && $days > 14 && $days <= 30,
                                'text-green-600' => $days && $days <= 14,
                                'text-gray-500' => !$days,
                            ])>
                                @if ($days)
                                    {{ $days }}日前
                                @else
                                    未訪問
                                @endif
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-900">
                                {{ $client->contact_person }}
                                @if ($client->department || $client->position)
                                    <span class="text-gray-500">
                                        ({{ $client->department }}{{ $client->position ? ' / ' . $client->position : '' }})
                                    </span>
                                @endif
                            </div>
                            <div class="text-sm text-gray-500 space-y-1">
                                @if ($client->phone)
                                    <div>📱 {{ $client->phone }}</div>
                                @endif
                                @if ($client->fax)
                                    <div>📠 {{ $client->fax }}</div>
                                @endif
                                @if ($client->email)
                                    <div>✉️ {{ $client->email }}</div>
                                @endif
                            </div>
                            @if ($client->notes)
                                <div class="mt-2 text-sm text-gray-500 italic">
                                    {{ Str::limit($client->notes, 50) }}
                                </div>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                            <a href="#" class="text-blue-600 hover:text-blue-900">詳細</a>
                            <a href="#" class="ml-4 text-green-600 hover:text-green-900">訪問記録</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
