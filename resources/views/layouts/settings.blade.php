<x-app-layout>
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                <!-- サイドバーナビゲーション -->
                <div class="md:col-span-1">
                    <x-settings.navigation />
                </div>

                <!-- メインコンテンツ -->
                <div class="md:col-span-3">
                    {{ $slot }}
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
