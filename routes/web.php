<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::middleware(['auth', 'verified'])->group(function () {
    Volt::route('/', 'salesmanager')->name('dashboard');
    Volt::route('/home', 'salesmanager')->name('home');  // homeルートを追加
    Volt::route('/clients/create', 'clients.create')->name('clients.create');  // クライアント作成ルートを追加
    Volt::route('/clients/{clientId}', 'clients.details')->name('clients.detail');  // クライアント詳細ルートを追加
    Volt::route('/clients/{clientId}/visits/create', 'visits.create')->name('visits.create');  // 訪問記録作成ルートを追加
    Volt::route('/appointments', 'appointments.index')->name('appointments.index');  // アポイントメント一覧ルートを追加
    Volt::route('/appointments/create', 'appointments.create')->name('appointments.create');  // アポイントメント作成ルートを追加

    Route::redirect('settings', 'settings/profile');
    Volt::route('settings/profile', 'settings.profile')->name('settings.profile');
    Volt::route('settings/password', 'settings.password')->name('settings.password');
    Volt::route('settings/appearance', 'settings.appearance')->name('settings.appearance');
});

require __DIR__ . '/auth.php';
