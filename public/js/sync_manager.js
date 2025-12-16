(function() {
    'use strict';

    const SYNC_INTERVAL = 60000; // 1 minuto
    let isSyncing = false;
    let syncTimer = null;

    /**
     * Verifica se há conexão real com a internet
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
            console.log('[Sync] Sem conexão real:', error.message);
            return false;
        }
    }

    /**
     * Obter CSRF token
     */
    function getCsrf() {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") || "";
    }

    /**
     * Obter token de autenticação
     */
    async function getAuthToken() {
        return await window.OfflineData.getAuthToken();
    }

    /**
     * Sincroniza a fila de operações pendentes
     */
    async function syncQueue() {
        if (isSyncing) {
            console.log('[Sync] Já está sincronizando...');
            return { success: false, message: 'Sync já em andamento' };
        }
        
        const online = await hasRealInternet();
        if (!online) {
            console.log("[Sync] Offline ou servidor inacessível");
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

            // Filtrar apenas operações não sincronizadas
            const pendingQueue = queue.filter(op => !op.synced);
            
            if (pendingQueue.length === 0) {
                console.log('[Sync] Todas operações já sincronizadas');
                isSyncing = false;
                return { success: true, message: 'Tudo sincronizado' };
            }

            console.log(`[Sync] Sincronizando ${pendingQueue.length} operações...`);

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

            // Marcar operações como sincronizadas ou remover da fila
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

            // Limpar operações antigas já sincronizadas (mais de 7 dias)
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

            // Disparar evento de sincronização concluída
            window.dispatchEvent(new CustomEvent('sync-completed', { 
                detail: { 
                    success: true, 
                    syncedCount: pendingQueue.length,
                    results: result 
                } 
            }));

            isSyncing = false;
            return { success: true, message: `${pendingQueue.length} operações sincronizadas`, results: result };

        } catch (error) {
            console.error("[Sync] Exceção:", error);
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
     * Inicia o gerenciador de sincronização
     */
    function startSyncManager() {
        // Sincronizar IMEDIATAMENTE quando voltar online
        window.addEventListener('online', () => {
            console.log('[Sync] Conexão detectada - sincronizando IMEDIATAMENTE');
            setTimeout(syncQueue, 500); // Delay mínimo de 500ms para estabilizar
        });

        // Sincronização periódica (a cada 30 segundos quando online)
        if (syncTimer) {
            clearInterval(syncTimer);
        }
        
        syncTimer = setInterval(() => {
            if (navigator.onLine) {
                syncQueue();
            }
        }, 30000); // 30 segundos

        // Sincronização inicial (se online)
        if (navigator.onLine) {
            setTimeout(syncQueue, 2000);
        }

        // Sincronizar antes de fechar a página (se online)
        window.addEventListener('beforeunload', () => {
            if (navigator.onLine) {
                // Tentativa de sincronização antes de sair
                // Nota: pode não completar se a página fechar muito rápido
                syncQueue();
            }
        });

        // Sincronizar quando a aba voltar a ficar visível
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden && navigator.onLine) {
                console.log('[Sync] Aba visível novamente - verificando sincronização');
                setTimeout(syncQueue, 1000);
            }
        });

        console.log("[Sync] Manager iniciado com sincronização automática");
    }

    /**
     * Para o gerenciador de sincronização
     */
    function stopSyncManager() {
        if (syncTimer) {
            clearInterval(syncTimer);
            syncTimer = null;
        }
        console.log("[Sync] Manager parado");
    }

    /**
     * Sincronização manual
     */
    async function forceSyncNow() {
        console.log('[Sync] Sincronização manual solicitada');
        return await syncQueue();
    }

    /**
     * Obter status da fila
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