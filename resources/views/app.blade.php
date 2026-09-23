<!doctype html>
<html lang="hu">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Mini-ERP · Szilágyi Roland</title>
    <meta name="description" content="Moduláris Laravel 13 mini-ERP: rendelés, készlet, CRM, NAV Online Számla 3.0, AI-asszisztens.">
    <meta name="theme-color" content="#f7f7f8" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="#09090b" media="(prefers-color-scheme: dark)">
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='8' fill='%234f46e5'/%3E%3Cpath d='M9 11h14M9 16h10M9 21h14' stroke='white' stroke-width='2.6' stroke-linecap='round'/%3E%3C/svg%3E">
    <script>
        // Téma a festés előtt: nincs villanás. Alapértelmezés a rendszerbeállítás.
        (() => {
            let t = null;
            try { t = localStorage.getItem('erp-theme'); } catch (e) {}
            const dark = t ? t === 'dark' : window.matchMedia('(prefers-color-scheme: dark)').matches;
            document.documentElement.classList.toggle('dark', dark);
        })();
    </script>
    {{-- A magyar ékezetekhez a latin és a latin-ext betűkészlet is kell: előtöltve nincs betűcsere-ugrás. --}}
    @foreach (['latin', 'latin-ext'] as $subset)
        <link rel="preload" as="font" type="font/woff2" crossorigin href="{{ Vite::asset("node_modules/@fontsource-variable/inter/files/inter-{$subset}-wght-normal.woff2") }}">
    @endforeach
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <div id="app"></div>
</body>
</html>
