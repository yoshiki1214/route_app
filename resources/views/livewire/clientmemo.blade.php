<?php

use function Livewire\Volt\{state, mount};
use App\Models\{Client, Memo};
use Carbon\Carbon;

state([
    'client' => null,
    'memos' => [],
    'title' => '',
    'content' => '',
    'category' => '',
    'is_important' => false,
    'reminder_at' => null,
    'showForm' => false,
]);

mount(function (Client $client) {
    $this->client = $client;
    $this->loadMemos();
});

$loadMemos = function () {
    $this->memos = $this->client->memos()->with('user')->latest()->get();
};

$toggleForm = function () {
    $this->showForm = !$this->showForm;
    if (!$this->showForm) {
        $this->resetForm();
    }
};

$resetForm = function () {
    $this->title = '';
    $this->content = '';
    $this->category = '';
    $this->is_important = false;
    $this->reminder_at = null;
};

$saveMemo = function () {
    $this->validate([
        'title' => 'required|max:255',
        'content' => 'required',
        'category' => 'nullable|max:255',
        'reminder_at' => 'nullable|date',
    ]);

    $this->client->memos()->create([
        'user_id' => auth()->id(),
        'title' => $this->title,
        'content' => $this->content,
        'category' => $this->category,
        'is_important' => $this->is_important,
        'reminder_at' => $this->reminder_at,
    ]);

    $this->loadMemos();
    $this->toggleForm();
};

?>

<div class="p-4">
    <div class="flex justify-between items-center mb-4">
        <h2 class="text-xl font-bold">{{ $client->name }} のメモ</h2>
        <button wire:click="toggleForm" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
            {{ $showForm ? 'キャンセル' : '新規メモ' }}
        </button>
    </div>

    @if ($showForm)
        <div class="bg-white rounded-lg shadow p-4 mb-6">
            <form wire:submit="saveMemo" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">タイトル</label>
                    <input type="text" wire:model="title"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                    @error('title')
                        <span class="text-red-600 text-sm">{{ $message }}</span>
                    @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">内容</label>
                    <textarea wire:model="content" rows="4" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"></textarea>
                    @error('content')
                        <span class="text-red-600 text-sm">{{ $message }}</span>
                    @enderror
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">カテゴリ</label>
                        <input type="text" wire:model="category"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">リマインダー</label>
                        <input type="datetime-local" wire:model="reminder_at"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                    </div>
                </div>

                <div class="flex items-center">
                    <input type="checkbox" wire:model="is_important"
                        class="rounded border-gray-300 text-blue-600 shadow-sm">
                    <label class="ml-2 text-sm text-gray-700">重要</label>
                </div>

                <div class="flex justify-end">
                    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                        保存
                    </button>
                </div>
            </form>
        </div>
    @endif

    <div class="space-y-4">
        @foreach ($memos as $memo)
            <div @class([
                'bg-white rounded-lg shadow p-4',
                'border-l-4 border-red-500' => $memo->is_important,
            ])>
                <div class="flex justify-between items-start">
                    <div>
                        <h3 class="text-lg font-semibold">{{ $memo->title }}</h3>
                        <p class="text-sm text-gray-500">
                            {{ $memo->user->name }} • {{ $memo->created_at->format('Y/m/d H:i') }}
                            @if ($memo->category)
                                • {{ $memo->category }}
                            @endif
                        </p>
                    </div>
                    @if ($memo->reminder_at)
                        <div class="text-sm text-blue-600">
                            ⏰ {{ Carbon::parse($memo->reminder_at)->format('Y/m/d H:i') }}
                        </div>
                    @endif
                </div>
                <div class="mt-2 text-gray-700 whitespace-pre-line">{{ $memo->content }}</div>
            </div>
        @endforeach
    </div>
</div>
