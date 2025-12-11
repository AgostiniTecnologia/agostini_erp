/* public/js/sync_manager.js
 * Gerenciador de sincronizaÃ§Ã£o offline
 */

(function() {
    'use strict';

    const SYNC_INTERVAL = 60000; // 1 minuto
    let isSyncing = false;
    let syncTimer = null;

    /**
     * Verifica se hÃ¡ conexÃ£o real com a internet
     */
    async function hasRealInternet() {
        if (!navigator.onLine) {
            return false;
        }
        
        try {
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 5000);
            
            const response = await fetch('/api/ping', { 
                method: 'HEAD',
                cache: 'no-store',
                signal: controller.signal
            });
            
            clearTimeout(timeoutId);
            return response && response.ok;
        } catch (error) {
            console.log('[Sync] Sem conexÃ£o real:', error.message);
            return false;
        }
    }

    /**
     * ObtÃ©m CSRF token
     */
    function getCsrf() {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") || "";
    }

    /**
     * ObtÃ©m token de autenticaÃ§Ã£o
     */
    async function getAuthToken() {
        return await window.OfflineData.getAuthToken();
    }

    /**
     * Sincroniza a fila de operaÃ§Ãµes pendentes
     */
    async function syncQueue() {
        if (isSyncing) {
            console.log('[Sync] JÃ¡ estÃ¡ sincronizando...');
            return { success: false, message: 'Sync jÃ¡ em andamento' };
        }
        
        const online = await hasRealInternet();
        if (!online) {
            console.log("[Sync] Offline ou servidor inacessÃ­vel");
            return { success: false, message: 'Offline' };
        }

        isSyncing = true;
        
        try {
            let queue = await localforage.getItem('sync_queue') || [];
            
            if (!Array.isArray(queue) || queue.length === 0) {
                console.log('[Sync] Fila vazia');
                isSyncing = false;
                return { success: true, message: 'Nada para sincronizar' };
            }

            // Filtrar apenas operaÃ§Ãµes nÃ£o sincronizadas
            const pendingQueue = queue.filter(op => !op.synced);
            
            if (pendingQueue.length === 0) {
                console.log('[Sync] Todas operaÃ§Ãµes jÃ¡ sincronizadas');
                isSyncing = false;
                return { success: true, message: 'Tudo sincronizado' };
            }

            console.log(`[Sync] Sincronizando ${pendingQueue.length} operaÃ§Ãµes...`);

            const token = await getAuthToken();
            
            const response = await fetch('/api/offline-sync', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrf(),
                    'Authorization': token ? `Bearer ${token}` : '',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ queue: pendingQueue })
            });

            if (!response.ok) {
                console.error("[Sync] HTTP Error:", response.status, await response.text());
                isSyncing = false;
                return { success: false, message: `Erro HTTP: ${response.status}` };
            }

            const result = await response.json();
            console.log("[Sync] Resultado:", result);

            // Marcar operaÃ§Ãµes como sincronizadas ou remover da fila
            const updatedQueue = queue.map(op => {
                const wasSynced = pendingQueue.some(p => 
                    p.timestamp === op.timestamp && 
                    p.storeName === op.storeName
                );
                
                if (wasSynced) {
                    return { ...op, synced: true };
                }
                return op;
            });

            await localforage.setItem('sync_queue', updatedQueue);

            // Limpar operaÃ§Ãµes antigas jÃ¡ sincronizadas (mais de 7 dias)
            const sevenDaysAgo = new Date();
            sevenDaysAgo.setDate(sevenDaysAgo.getDate() - 7);
            
            const cleanedQueue = updatedQueue.filter(op => {
                if (op.synced) {
                    const opDate = new Date(op.timestamp);
                    return opDate > sevenDaysAgo;
                }
                return true;
            });

            await localforage.setItem('sync_queue', cleanedQueue);

            // Atualizar dados locais com dados do servidor
            await refreshLocalData();

            // Disparar evento de sincronizaÃ§Ã£o concluÃ­da
            window.dispatchEvent(new CustomEvent('sync-completed', { 
                detail: { 
                    success: true, 
                    syncedCount: pendingQueue.length,
                    results: result 
                } 
            }));

            isSyncing = false;
            return { success: true, message: `${pendingQueue.length} operaÃ§Ãµes sincronizadas`, results: result };

        } catch (error) {
            console.error("[Sync] ExceÃ§Ã£o:", error);
            isSyncing = false;
            
            window.dispatchEvent(new CustomEvent('sync-error', { 
                detail: { error: error.message } 
            }));
            
            return { success: false, message: error.message };
        }
    }

    /**
     * Atualiza dados locais com dados frescos do servidor
     */
    async function refreshLocalData() {
        try {
            const token = await getAuthToken();
            if (!token) return;

            // Atualizar clientes
            try {
                const clientsResp = await fetch('/api/clients', {
                    headers: {
                        'Authorization': `Bearer ${token}`,
                        'Accept': 'application/json'
                    }
                });
                
                if (clientsResp.ok) {
                    const clients = await clientsResp.json();
                    await window.OfflineData.storeData('clients', clients.data || clients);
                }
            } catch (e) {
                console.warn('[Sync] Erro ao atualizar clientes:', e);
            }

            // Atualizar produtos
            try {
                const productsResp = await fetch('/api/products', {
                    headers: {
                        'Authorization': `Bearer ${token}`,
                        'Accept': 'application/json'
                    }
                });
                
                if (productsResp.ok) {
                    const products = await productsResp.json();
                    await window.OfflineData.storeData('products', products.data || products);
                }
            } catch (e) {
                console.warn('[Sync] Erro ao atualizar produtos:', e);
            }

            console.log('[Sync] Dados locais atualizados');
        } catch (error) {
            console.error('[Sync] Erro ao atualizar dados locais:', error);
        }
    }

    /**
     * Inicia o gerenciador de sincronizaÃ§Ã£o
     */
    function startSyncManager() {
        // Sincronizar IMEDIATAMENTE quando voltar online
        window.addEventListener('online', () => {
            console.log('[Sync] ðŸŒ ConexÃ£o detectada - sincronizando IMEDIATAMENTE');
            setTimeout(syncQueue, 500); // Delay mÃ­nimo de 500ms para estabilizar
        });

        // SincronizaÃ§Ã£o periÃ³dica (a cada 30 segundos quando online)
        if (syncTimer) {
            clearInterval(syncTimer);
        }
        
        syncTimer = setInterval(() => {
            if (navigator.onLine) {
                syncQueue();
            }
        }, 30000); // 30 segundos

        // SincronizaÃ§Ã£o inicial (se online)
        if (navigator.onLine) {
            setTimeout(syncQueue, 2000);
        }

        // Sincronizar antes de fechar a pÃ¡gina (se online)
        window.addEventListener('beforeunload', () => {
            if (navigator.onLine) {
                // Tentativa de sincronizaÃ§Ã£o antes de sair
                // Nota: pode nÃ£o completar se a pÃ¡gina fechar muito rÃ¡pido
                syncQueue();
            }
        });

        // Sincronizar quando a aba voltar a ficar visÃ­vel
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden && navigator.onLine) {
                console.log('[Sync] ðŸ‘ï¸ Aba visÃ­vel novamente - verificando sincronizaÃ§Ã£o');
                setTimeout(syncQueue, 1000);
            }
        });

        console.log("[Sync] Manager iniciado com sincronizaÃ§Ã£o automÃ¡tica");
    }

    /**
     * Para o gerenciador de sincronizaÃ§Ã£o
     */
    function stopSyncManager() {
        if (syncTimer) {
            clearInterval(syncTimer);
            syncTimer = null;
        }
        console.log("[Sync] Manager parado");
    }

    /**
     * ForÃ§a sincronizaÃ§Ã£o manual
     */
    async function forceSyncNow() {
        console.log('[Sync] SincronizaÃ§Ã£o manual solicitada');
        return await syncQueue();
    }

    /**
     * ObtÃ©m status da fila
     */
    async function getSyncQueueStatus() {
        try {
            const queue = await localforage.getItem('sync_queue') || [];
            const pending = queue.filter(op => !op.synced);
            
            return {
                total: queue.length,
                pending: pending.length,
                synced: queue.length - pending.length,
                isSyncing
            };
        } catch (error) {
            console.error('[Sync] Erro ao obter status:', error);
            return { total: 0, pending: 0, synced: 0, isSyncing: false };
        }
    }

    // Expor API globalmente
    window.SyncManager = { 
        startSyncManager, 
        stopSyncManager,
        syncQueue,
        forceSyncNow,
        getSyncQueueStatus,
        hasRealInternet
    };

    // Iniciar automaticamente
    startSyncManager();

})();