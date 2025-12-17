/* public/js/livewire_offline_bridge.js
 * Ponte entre Livewire e OfflineData
 */

(function () {
    'use strict';

    let bridgeInitialized = false;

    /**
     * Inicializa a ponte Livewire <-> OfflineData
     */
    function initBridge() {
        if (bridgeInitialized) {
            console.log('[LivewireBridge] Já inicializado');
            return;
        }

        if (typeof Livewire === 'undefined') {
            console.log('[LivewireBridge] Aguardando Livewire...');
            setTimeout(initBridge, 250);
            return;
        }

        if (typeof window.OfflineData === 'undefined') {
            console.log('[LivewireBridge] Aguardando OfflineData...');
            setTimeout(initBridge, 250);
            return;
        }

        bridgeInitialized = true;
        console.log('[LivewireBridge] Inicializando...');

        // ==========================================
        // Eventos do Livewire -> JS
        // ==========================================

        /**
         * Livewire solicita dados offline
         * Payload: { storeName: 'clients' } ou apenas 'clients'
         */
        Livewire.on('fetchOfflineData', async (payload) => {
            console.log('[LivewireBridge] fetchOfflineData recebido:', payload);
            
            let storeName = null;
            
            // Lidar com diferentes formatos de payload
            if (typeof payload === 'string') {
                storeName = payload;
            } else if (payload && payload.storeName) {
                storeName = payload.storeName;
            } else if (payload && payload.detail && payload.detail.storeName) {
                storeName = payload.detail.storeName;
            } else if (Array.isArray(payload) && payload.length > 0) {
                // Livewire v3 as vezes envia array
                if (typeof payload[0] === 'string') {
                    storeName = payload[0];
                } else if (payload[0] && payload[0].storeName) {
                    storeName = payload[0].storeName;
                }
            }
            
            if (!storeName) {
                console.warn('[LivewireBridge] fetchOfflineData sem storeName válido', payload);
                Livewire.dispatch('setOfflineData', { data: [] });
                return;
            }
            
            try {
                const data = await window.OfflineData.retrieveData(storeName);
                console.log(`[LivewireBridge] Enviando dados para Livewire: ${storeName} (${data.length} itens)`);
                Livewire.dispatch('setOfflineData', { data });
            } catch (error) {
                console.error('[LivewireBridge] Erro ao buscar dados:', error);
                Livewire.dispatch('setOfflineData', { data: [] });
            }
        });

        /**
         * Livewire adiciona operação à fila de sincronização
         * Payload: { storeName, action, payload }
         */
        Livewire.on('addToSyncQueue', async (payload) => {
            console.log('[LivewireBridge] addToSyncQueue recebido:', payload);
            
            let storeName, action, data;
            
            // Extrair dados do payload (suporta múltiplos formatos)
            if (Array.isArray(payload) && payload.length > 0) {
                const item = payload[0];
                storeName = item.storeName || item.detail?.storeName;
                action = item.action || item.detail?.action;
                data = item.payload || item.data || item.detail?.payload;
            } else {
                storeName = payload?.storeName || payload?.detail?.storeName;
                action = payload?.action || payload?.detail?.action;
                data = payload?.payload || payload?.data || payload?.detail?.payload;
            }
            
            if (!storeName || !action || !data) {
                console.warn('[LivewireBridge] addToSyncQueue payload inválido:', payload);
                return;
            }
            
            try {
                await window.OfflineData.addToSyncQueue(storeName, action, data);
                console.log(`[LivewireBridge] Operação adicionada: ${action} ${storeName}`);
            } catch (error) {
                console.error('[LivewireBridge] Erro ao adicionar à fila:', error);
            }
        });

        /**
         * Livewire salva dados offline (upsert)
         */
        Livewire.on('saveOfflineData', async (payload) => {
            console.log('[LivewireBridge] saveOfflineData recebido:', payload);
            
            let storeName, item;
            
            if (Array.isArray(payload) && payload.length > 0) {
                const p = payload[0];
                storeName = p.storeName || p.detail?.storeName;
                item = p.item || p.data || p.detail?.item;
            } else {
                storeName = payload?.storeName || payload?.detail?.storeName;
                item = payload?.item || payload?.data || payload?.detail?.item;
            }
            
            if (!storeName || !item) {
                console.warn('[LivewireBridge] saveOfflineData payload inválido:', payload);
                return;
            }
            
            try {
                await window.OfflineData.upsertItem(storeName, item);
                console.log(`[LivewireBridge] Item salvo offline: ${storeName}`);
            } catch (error) {
                console.error('[LivewireBridge] Erro ao salvar item:', error);
            }
        });

        /**
         * Livewire deleta dado offline
         */
        Livewire.on('deleteOfflineData', async (payload) => {
            console.log('[LivewireBridge] deleteOfflineData recebido:', payload);
            
            let storeName, itemId;
            
            if (Array.isArray(payload) && payload.length > 0) {
                const p = payload[0];
                storeName = p.storeName || p.detail?.storeName;
                itemId = p.itemId || p.id || p.detail?.itemId;
            } else {
                storeName = payload?.storeName || payload?.detail?.storeName;
                itemId = payload?.itemId || payload?.id || payload?.detail?.itemId;
            }
            
            if (!storeName || !itemId) {
                console.warn('[LivewireBridge] deleteOfflineData payload inválido:', payload);
                return;
            }
            
            try {
                await window.OfflineData.deleteItem(storeName, itemId);
                console.log(`[LivewireBridge] Item deletado offline: ${storeName} - ${itemId}`);
            } catch (error) {
                console.error('[LivewireBridge] Erro ao deletar item:', error);
            }
        });

        // ==========================================
        // Eventos JS -> Livewire
        // ==========================================

        /**
         * Quando dados offline são atualizados, notificar Livewire
         */
        window.addEventListener('offline-data-updated', (event) => {
            const detail = event.detail || {};
            console.log('[LivewireBridge] offline-data-updated:', detail);
            
            try {
                Livewire.dispatch('offline-data-updated', {
                    storeName: detail.storeName,
                    action: detail.action,
                    payload: detail.payload
                });
            } catch (error) {
                console.error('[LivewireBridge] Erro ao despachar evento:', error);
            }
        });

        /**
         * Quando sincronização é concluí­da
         */
        window.addEventListener('sync-completed', (event) => {
            const detail = event.detail || {};
            console.log('[LivewireBridge] sync-completed:', detail);
            
            try {
                Livewire.dispatch('sync-completed', detail);
            } catch (error) {
                console.error('[LivewireBridge] Erro ao despachar sync-completed:', error);
            }
        });

        /**
         * Quando hÃ¡ erro na sincronização
         */
        window.addEventListener('sync-error', (event) => {
            const detail = event.detail || {};
            console.log('[LivewireBridge] sync-error:', detail);
            
            try {
                Livewire.dispatch('sync-error', detail);
            } catch (error) {
                console.error('[LivewireBridge] Erro ao despachar sync-error:', error);
            }
        });

        /**
         * Monitora mudanÃ§a de status online/offline
         */
        window.addEventListener('online', () => {
            console.log('[LivewireBridge] Status: ONLINE');
            try {
                Livewire.dispatch('connection-status-changed', { online: true });
            } catch (error) {
                console.error('[LivewireBridge] Erro ao despachar online:', error);
            }
        });

        window.addEventListener('offline', () => {
            console.log('[LivewireBridge] Status: OFFLINE');
            try {
                Livewire.dispatch('connection-status-changed', { online: false });
            } catch (error) {
                console.error('[LivewireBridge] Erro ao despachar offline:', error);
            }
        });

        console.log('[LivewireBridge] Inicializado com sucesso');
    }

    // Expor função de inicialização
    window.LivewireBridge = {
        init: initBridge
    };

    // Tentar inicializar
    if (document.readyState === 'loading') {
        document.addEventListener('livewire:init', () => {
            Livewire.hook('message.failed', () => {
        if (!navigator.onLine) {
            alert('Você está offline. Use o módulo offline para cadastro.');
            }
        });
    });
});