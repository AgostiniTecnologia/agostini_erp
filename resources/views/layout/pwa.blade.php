<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    {{-- CSRF --}}
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- Título Dinâmico --}}
    <title>{{ config('app.name', 'Agostini ERP') }}</title>

    {{-- Manifest PWA --}}
    <link rel="manifest" href="/manifest.json">

    {{-- Tema --}}
    <meta name="theme-color" content="#0f172a">

    {{-- Ãcones --}}
    <link rel="icon" sizes="192x192" href="/images/icons/icon-192x192.png">
    <link rel="apple-touch-icon" href="/images/icons/icon-192x192.png">

    {{-- Estilos do sistema --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])

</head>
<body class="antialiased bg-gray-100">

    {{ $slot }}

    {{-- LOCALFORAGE (CDN OFICIAL) --}}
    <script src="https://cdnjs.cloudflare.com/ajax/libs/localforage/1.10.0/localforage.min.js"></script>

    {{-- SCRIPTS OFFLINE --}}
    <script src="/js/offline_data.js"></script>
    <script src="/js/sync_manager.js"></script>
    <script src="/js/livewire_offline_bridge.js"></script>

    {{-- REGISTRO DO SERVICE WORKER --}}
    <script>
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('/serviceworker.js', { scope: '/' })
                .then(reg => console.log("PWA registrado com sucesso:", reg.scope))
                .catch(err => console.error("Erro ao registrar Service Worker:", err));
        } else {
            console.warn("Service Worker não suportado no navegador.");
        }
    </script>

</body>
</html>