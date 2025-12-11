<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>{{ config('app.name', 'Agostini ERP') }}</title>

    {{-- CSRF Token --}}
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- Manifest --}}
    <link rel="manifest" href="/manifest.json">

    {{-- PWA Meta Tags --}}
    <meta name="theme-color" content="#2563eb">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="{{ config('app.name') }}">
    <link rel="apple-touch-icon" href="/images/icons/icon-192x192.png">

    {{-- Filament / Livewire Styles --}}
    @filamentStyles
    @vite('resources/css/app.css')

    <style>
        /* Garantir responsividade em PWA */
        html, body {
            height: 100%;
            background: #f8fafc;
        }
        
        /* Indicador de status offline */
        #offline-indicator {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: #f59e0b;
            color: white;
            padding: 8px;
            text-align: center;
            font-size: 14px;
            font-weight: 500;
            z-index: 9999;
            display: none;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
            animation: slideDown 0.3s ease;
        }
        
        #offline-indicator.show {
            display: block;
        }
        
        #sync-indicator {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: #3b82f6;
            color: white;
            padding: 12px 20px;
            border-radius: 8px;
            font-size: 14px;
            z-index: 9999;
            display: none;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            animation: slideIn 0.3s ease;
        }
        
        #sync-indicator.show {
            display: block;
        }
        
        #sync-indicator.success {
            background: #10b981;
        }
        
        #sync-indicator.error {
            background: #ef4444;
        }
        
        @keyframes slideIn {
            from {
                transform: translateY(100px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
        
        @keyframes slideDown {
            from {
                transform: translateY(-100%);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
    </style>
</head>

<body class="font-sans antialiased">

    {{-- Indicador de status offline --}}
    <div id="offline-indicator">
        Sem conexão - alterações serão sincronizadas automaticamente quando reconectar
    </div>

    {{-- Indicador de Sincronização --}}
    <div id="sync-indicator">
        Sincronizando...
    </div>

    {{ $slot }}

    {{-- Livewire + Filament Scripts --}}
    @filamentScripts
    @vite('resources/js/app.js')

    {{-- ========================= --}}
    {{--    PWA - SERVICE WORKER   --}}
    {{-- ========================= --}}
    <script>
        // Registrar Service Worker
        if ("serviceWorker" in navigator) {
            window.addEventListener("load", function () {
                navigator.serviceWorker.register("/serviceworker.js")
                    .then(function (registration) {
                        console.log("ServiceWorker registrado:", registration.scope);
                        
                        // Verificar atualização
                        registration.addEventListener('updatefound', () => {
                            const newWorker = registration.installing;
                            newWorker.addEventListener('statechange', () => {
                                if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                                    // Nova versão disponível
                                    if (confirm('Nova versão disponível! Deseja atualizar?')) {
                                        newWorker.postMessage({ type: 'SKIP_WAITING' });
                                        window.location.reload();
                                    }
                                }
                            });
                        });
                    })
                    .catch(function (error) {
                        console.error("Falha ao registrar ServiceWorker:", error);
                    });
            });
        }
    </script>

    {{-- ================================= --}}
    {{--  SCRIPTS DO MODO OFFLINE (PWA)    --}}
    {{-- ================================= --}}

    {{-- IndexedDB / LocalForage --}}
    <script src="https://cdnjs.cloudflare.com/ajax/libs/localforage/1.10.0/localforage.min.js"></script>

    {{-- Scripts offline (ordem importa!) --}}
    <script src="/js/offline_data.js"></script>
    <script src="/js/livewire_offline_bridge.js"></script>
    <script src="/js/sync_manager.js"></script>
    <script src="/js/offline_form_interceptor.js"></script>

    {{-- ======================= --}}
    {{--  INDICADORES DE STATUS  --}}
    {{-- ======================= --}}
    <script>
        (function() {
            const offlineIndicator = document.getElementById('offline-indicator');
            const syncIndicator = document.getElementById('sync-indicator');
            
            // Monitorar status online/offline
            function updateOnlineStatus() {
                if (navigator.onLine) {
                    offlineIndicator.classList.remove('show');
                    console.log('[Status] ONLINE');
                } else {
                    offlineIndicator.classList.add('show');
                    console.log('[Status] OFFLINE');
                }
            }
            
            window.addEventListener('online', () => {
                updateOnlineStatus();
                // Mostrar notificação de reconexão
                showSyncNotification('Conectado! Sincronizando...', 'syncing');
            });
            
            window.addEventListener('offline', updateOnlineStatus);
            
            // Status inicial
            updateOnlineStatus();
            
            // Função para mostrar notificação de Sincronização
            function showSyncNotification(message, type = 'syncing') {
                syncIndicator.textContent = message;
                syncIndicator.className = 'show';
                
                if (type === 'success') {
                    syncIndicator.classList.add('success');
                } else if (type === 'error') {
                    syncIndicator.classList.add('error');
                }
            }
            
            // Função para esconder notificação
            function hideSyncNotification(delay = 3000) {
                setTimeout(() => {
                    syncIndicator.classList.remove('show', 'success', 'error');
                }, delay);
            }
            
            // Monitorar quando Sincronização inicia
            let syncCheckInterval = setInterval(async () => {
                if (typeof window.SyncManager !== 'undefined') {
                    const status = await window.SyncManager.getSyncQueueStatus();
                    
                    if (status.isSyncing && status.pending > 0) {
                        showSyncNotification(`Sincronizando ${status.pending} operação...`, 'syncing');
                    }
                }
            }, 1000);
            
            // Monitorar Sincronização concluída
            window.addEventListener('sync-completed', (e) => {
                const detail = e.detail || {};
                const count = detail.syncedCount || 0;
                
                console.log('[Sync] Sincronização concluída:', count, 'operação');
                
                showSyncNotification(`${count} operação sincronizadas!`, 'success');
                hideSyncNotification(4000);
                
                // Recarregar página para atualizar dados (opcional)
                // setTimeout(() => window.location.reload(), 2000);
            });
            
            // Monitorar erro na Sincronização
            window.addEventListener('sync-error', (e) => {
                const detail = e.detail || {};
                console.error('[Sync] Erro:', detail.error);
                
                showSyncNotification('Erro ao sincronizar', 'error');
                hideSyncNotification(5000);
            });
            
        })();
    </script>

    {{-- ============================= --}}
    {{--  CARREGAMENTO NO LOGIN        --}}
    {{-- ============================= --}}
    @auth
    <script>
        (function() {
            // Salvar token no primeiro carregamento
            const metaCsrf = document.querySelector('meta[name="csrf-token"]');
            if (metaCsrf && typeof window.OfflineData !== 'undefined') {
                const token = metaCsrf.getAttribute('content');
                
                // Salvar dados do usuÃ¡rio
                const userData = {
                    name: '{{ auth()->user()->name ?? '' }}',
                    username: '{{ auth()->user()->username ?? '' }}',
                    uuid: '{{ auth()->user()->uuid ?? '' }}'
                };
                
                window.OfflineData.saveUserData(userData);
                
                //carregar dados essenciais
                if (navigator.onLine) {
                    console.log('[App] Iniciando - carregamento de dados...');
                    window.OfflineData.preloadEssentialData();
                }
            }
        })();
    </script>
    @endauth

</body>
</html>