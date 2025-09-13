<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>{{ $title ?? config('app.name') }}</title>

<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">

<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance

@if (config('services.google_maps.api_key'))
    <script>
        // Google Maps APIの読み込みを遅延させる
        window.loadGoogleMaps = function() {
            if (window.initGoogleMaps) {
                const script = document.createElement('script');
                script.src =
                    'https://maps.googleapis.com/maps/api/js?key={{ config('services.google_maps.api_key') }}&libraries=places&callback=initGoogleMaps&v=weekly';
                script.async = true;
                script.defer = true;
                document.head.appendChild(script);
            } else {
                // initGoogleMaps関数がまだ定義されていない場合は少し待つ
                setTimeout(window.loadGoogleMaps, 100);
            }
        };
    </script>
@endif
