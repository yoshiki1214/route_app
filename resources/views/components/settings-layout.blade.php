<x-app-layout>
    <div class="settings-page">
        <div class="settings-page-wrapper">
            <div class="settings-layout">
                <!-- サイドバーナビゲーション -->
                <div class="settings-sidebar-wrapper">
                    <x-settings.navigation />
                </div>

                <!-- メインコンテンツ -->
                <div class="settings-content-wrapper">
                    {{ $slot }}
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
