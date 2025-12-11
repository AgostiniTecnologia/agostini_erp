/* public/js/pwa_helper.js
 * UtilitÃ¡rios e helpers para gerenciar PWA
 * Carregue este arquivo APÃ“S os scripts principais
 */

(function() {
    'use strict';

    /**
     * UtilitÃ¡rios PWA
     */
    const PWAHelper = {
        
        /**
         * Verifica se estÃ¡ rodando como PWA instalado
         */
        isPWA: function() {
            return window.matchMedia('(display-mode: standalone)').matches ||
                   window.navigator.standalone === true;
        },

        /**
         * Verifica se PWA pode ser instalado
         */
        canInstall: function() {
            return 'serviceWorker' in navigator && 'PushManager' in window;
        },

        /**
         * Solicita permissÃ£o para notificaÃ§Ãµes
         */
        requestNotificationPermission: async function() {
            if (!('Notification' in window)) {
                console.warn('[PWA] NotificaÃ§Ãµes nÃ£o suportadas');
                return false;
            }

            if (Notification.permission === 'granted') {
                return true;
            }

            if (Notification.permission !== 'denied') {
                const permission = await Notification.requestPermission();
                return permission === 'granted';
            }

            return false;
        },

        /**
         * Envia notificaÃ§Ã£o local
         */
        sendNotification: function(title, options = {}) {
            if (Notification.permission !== 'granted') {
                console.warn('[PWA] PermissÃ£o de notificaÃ§Ã£o negada');
                return;
            }

            const defaultOptions = {
                icon: '/images/icons/icon-192x192.png',
                badge: '/images/icons/icon-96x96.png',
                vibrate: [200, 100, 200],
                ...options
            };

            new Notification(title, defaultOptions);
        },

        /**
         * Limpa todos os caches
         */
        clearAllCaches: async function() {
            try {
                const cacheNames = await caches.keys();
                await Promise.all(
                    cacheNames.map(name => caches.delete(name))
                );
                console.log('[PWA] Todos os caches foram limpos');
                return true;
            } catch (error) {
                console.error('[PWA] Erro ao limpar caches:', error);
                return false;
            }
        },

        /**
         * Desregistra service worker
         */
        unregisterServiceWorker: async function() {
            try {
                const registrations = await navigator.serviceWorker.getRegistrations();
                
                for (let registration of registrations) {
                    await registration.unregister();
                }
                
                console.log('[PWA] Service Worker desregistrado');
                return true;
            } catch (error) {
                console.error('[PWA] Erro ao desregistrar SW:', error);
                return false;
            }
        },

        /**
         * Reinicia PWA (limpa cache e recarrega)
         */
        resetPWA: async function() {
            console.log('[PWA] Reiniciando aplicaÃ§Ã£o...');
            
            await this.clearAllCaches();
            await this.unregisterServiceWorker();
            
            // Recarregar pÃ¡gina
            window.location.reload(true);
        },

        /**
         * ObtÃ©m informaÃ§Ãµes do PWA
         */
        getInfo: async function() {
            const info = {
                isPWA: this.isPWA(),
                canInstall: this.canInstall(),
                isOnline: navigator.onLine,
                hasServiceWorker: 'serviceWorker' in navigator,
                notificationPermission: 'Notification' in window ? Notification.permission : 'not-supported',
                storage: {}
            };

            // InformaÃ§Ãµes de storage
            if ('storage' in navigator && 'estimate' in navigator.storage) {
                const estimate = await navigator.storage.estimate();
                info.storage = {
                    usage: estimate.usage,
                    quota: estimate.quota,
                    usagePercent: ((estimate.usage / estimate.quota) * 100).toFixed(2)
                };
            }

            // Service Worker
            if ('serviceWorker' in navigator) {
                const registration = await navigator.serviceWorker.getRegistration();
                if (registration) {
                    info.serviceWorker = {
                        active: !!registration.active,
                        waiting: !!registration.waiting,
                        installing: !!registration.installing,
                        scope: registration.scope
                    };
                }
            }

            // Dados offline
            if (typeof window.OfflineData !== 'undefined') {
                const stores = window.OfflineData.DATA_STORES;
                info.offlineData = {};
                
                for (const [key, storeName] of Object.entries(stores)) {
                    const data = await window.OfflineData.retrieveData(storeName);
                    info.offlineData[key] = Array.isArray(data) ? data.length : 'N/A';
                }
            }

            // Fila de sincronizaÃ§Ã£o
            if (typeof window.SyncManager !== 'undefined') {
                info.syncQueue = await window.SyncManager.getSyncQueueStatus();
            }

            return info;
        },

        /**
         * Exibe informaÃ§Ãµes no console
         */
        printInfo: async function() {
            const info = await this.getInfo();
            
            console.group('ðŸ“± PWA Information');
            console.log('Modo PWA:', info.isPWA ? 'âœ… Sim' : 'âŒ NÃ£o');
            console.log('Online:', info.isOnline ? 'âœ… Sim' : 'âŒ NÃ£o');
            console.log('Service Worker:', info.hasServiceWorker ? 'âœ… Suportado' : 'âŒ NÃ£o suportado');
            console.log('NotificaÃ§Ãµes:', info.notificationPermission);
            
            if (info.storage.quota) {
                console.log(`Storage: ${(info.storage.usage / 1024 / 1024).toFixed(2)} MB / ${(info.storage.quota / 1024 / 1024).toFixed(2)} MB (${info.storage.usagePercent}%)`);
            }
            
            if (info.serviceWorker) {
                console.group('Service Worker');
                console.log('Active:', info.serviceWorker.active);
                console.log('Waiting:', info.serviceWorker.waiting);
                console.log('Scope:', info.serviceWorker.scope);
                console.groupEnd();
            }
            
            if (info.offlineData) {
                console.group('Dados Offline');
                Object.entries(info.offlineData).forEach(([key, count]) => {
                    console.log(`${key}:`, count);
                });
                console.groupEnd();
            }
            
            if (info.syncQueue) {
                console.group('Fila de SincronizaÃ§Ã£o');
                console.log('Total:', info.syncQueue.total);
                console.log('Pendentes:', info.syncQueue.pending);
                console.log('Sincronizados:', info.syncQueue.synced);
                console.log('Sincronizando:', info.syncQueue.isSyncing ? 'Sim' : 'NÃ£o');
                console.groupEnd();
            }
            
            console.groupEnd();
            
            return info;
        },

        /**
         * Monitora mudanÃ§as de conexÃ£o
         */
        onConnectionChange: function(callback) {
            window.addEventListener('online', () => callback(true));
            window.addEventListener('offline', () => callback(false));
        },

        /**
         * Monitora atualizaÃ§Ãµes do Service Worker
         */
        onServiceWorkerUpdate: function(callback) {
            if (!('serviceWorker' in navigator)) return;

            navigator.serviceWorker.ready.then(registration => {
                registration.addEventListener('updatefound', () => {
                    const newWorker = registration.installing;
                    
                    newWorker.addEventListener('statechange', () => {
                        if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                            callback(newWorker);
                        }
                    });
                });
            });
        },

        /**
         * Prompt de instalaÃ§Ã£o PWA
         */
        setupInstallPrompt: function(onInstallPrompt) {
            let deferredPrompt;

            window.addEventListener('beforeinstallprompt', (e) => {
                e.preventDefault();
                deferredPrompt = e;
                
                if (typeof onInstallPrompt === 'function') {
                    onInstallPrompt(() => {
                        if (deferredPrompt) {
                            deferredPrompt.prompt();
                            deferredPrompt.userChoice.then((choiceResult) => {
                                console.log('[PWA] Install prompt:', choiceResult.outcome);
                                deferredPrompt = null;
                            });
                        }
                    });
                }
            });

            window.addEventListener('appinstalled', () => {
                console.log('[PWA] App instalado com sucesso');
                deferredPrompt = null;
            });
        }
    };

    // Expor globalmente
    window.PWAHelper = PWAHelper;

    // Auto-executar algumas funÃ§Ãµes
    if (PWAHelper.isPWA()) {
        console.log('ðŸŽ‰ Rodando como PWA instalado!');
    }

    // Comandos Ãºteis no console
    console.log('ðŸ’¡ Comandos disponÃ­veis:');
    console.log('  PWAHelper.printInfo() - Exibe informaÃ§Ãµes do PWA');
    console.log('  PWAHelper.resetPWA() - Reinicia PWA (limpa cache)');
    console.log('  PWAHelper.clearAllCaches() - Limpa todos os caches');
    console.log('  PWAHelper.sendNotification(title, options) - Envia notificaÃ§Ã£o');

})();