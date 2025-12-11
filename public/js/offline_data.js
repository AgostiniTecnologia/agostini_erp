/* public/js/offline_data.js
 * Gerenciador de dados offline usando LocalForage
 * IMPORTANTE: Este arquivo NÃƒO usa ES modules para compatibilidade com carregamento direto
 */

(function() {
    'use strict';

    // ConfiguraÃ§Ã£o do LocalForage
    localforage.config({
        name: "AgostiniERP",
        storeName: "offline_data",
        description: "Cache de dados offline",
    });

    // DefiniÃ§Ã£o de stores disponÃ­veis
    const DATA_STORES = {
        clients: "clients",
        products: "products",
        sales_visits: "sales_visits",
        sales_orders: "sales_orders",
        sales_order_items: "sales_order_items",
        scheduled_visits: "scheduled_visits",
        orders: "orders",
        financial_entries: "financial_entries",
        pricing_tables: "pricing_tables",
        sync_queue: "sync_queue",
        auth_token: "auth_token",
        user_data: "user_data"
    };

    /**
     * Salva dados em massa em um store
     */
    async function storeData(storeName, data) {
        try {
            await localforage.setItem(storeName, data);
            console.log(`[OfflineData] Store ${storeName} atualizado. itens=${Array.isArray(data) ? data.length : 'N/A'}`);
            
            window.dispatchEvent(new CustomEvent("offline-data-updated", { 
                detail: { storeName, action: "bulk", payload: data } 
            }));
            
            return true;
        } catch (error) {
            console.error(`[OfflineData] Erro ao salvar ${storeName}:`, error);
            return false;
        }
    }

    /**
     * Recupera dados de um store
     */
    async function retrieveData(storeName) {
        try {
            if (!DATA_STORES[storeName]) {
                console.warn(`[OfflineData] Store desconhecido: ${storeName}`);
                return [];
            }
            
            const data = await localforage.getItem(storeName);
            return data || [];
        } catch (error) {
            console.error(`[OfflineData] Erro ao carregar ${storeName}:`, error);
            return [];
        }
    }

    /**
     * Insere ou atualiza um item especÃ­fico
     */
    async function upsertItem(storeName, item) {
        if (!item || (typeof item.id === 'undefined' || item.id === null)) {
            console.error("[OfflineData] upsertItem: item invÃ¡lido", item);
            return false;
        }
        
        try {
            const data = (await retrieveData(storeName)) || [];
            const idx = data.findIndex(d => String(d.id) === String(item.id));
            
            if (idx !== -1) {
                data[idx] = { ...data[idx], ...item };
            } else {
                data.push(item);
            }
            
            await storeData(storeName, data);
            return true;
        } catch (error) {
            console.error("[OfflineData] Erro no upsertItem:", error);
            return false;
        }
    }

    /**
     * Remove um item especÃ­fico
     */
    async function deleteItem(storeName, itemId) {
        try {
            const data = (await retrieveData(storeName)) || [];
            const filtered = data.filter(d => String(d.id) !== String(itemId));
            
            await storeData(storeName, filtered);
            return true;
        } catch (error) {
            console.error("[OfflineData] Erro ao deletar item:", error);
            return false;
        }
    }

    /**
     * Adiciona operaÃ§Ã£o Ã  fila de sincronizaÃ§Ã£o
     */
    async function addToSyncQueue(storeName, action, payload) {
        if (!storeName || !action || !payload) {
            console.error("[OfflineData] addToSyncQueue: parÃ¢metros invÃ¡lidos", { storeName, action, payload });
            return false;
        }
        
        try {
            let queue = (await localforage.getItem("sync_queue")) || [];
            
            queue.push({ 
                storeName, 
                action, 
                payload, 
                timestamp: new Date().toISOString(),
                synced: false
            });
            
            await localforage.setItem("sync_queue", queue);
            console.log(`[OfflineData] + SyncQueue -> ${action} ${storeName}`, payload);
            
            window.dispatchEvent(new CustomEvent("offline-data-updated", { 
                detail: { storeName, action, payload } 
            }));
            
            return true;
        } catch (error) {
            console.error("[OfflineData] Erro ao adicionar na fila:", error);
            return false;
        }
    }

    /**
     * Salva token de autenticaÃ§Ã£o para uso offline
     */
    async function saveAuthToken(token) {
        try {
            await localforage.setItem('auth_token', token);
            return true;
        } catch (error) {
            console.error("[OfflineData] Erro ao salvar token:", error);
            return false;
        }
    }

    /**
     * Recupera token de autenticaÃ§Ã£o
     */
    async function getAuthToken() {
        try {
            return await localforage.getItem('auth_token');
        } catch (error) {
            console.error("[OfflineData] Erro ao recuperar token:", error);
            return null;
        }
    }

    /**
     * Salva dados do usuÃ¡rio
     */
    async function saveUserData(userData) {
        try {
            await localforage.setItem('user_data', userData);
            return true;
        } catch (error) {
            console.error("[OfflineData] Erro ao salvar dados do usuÃ¡rio:", error);
            return false;
        }
    }

    /**
     * Recupera dados do usuÃ¡rio
     */
    async function getUserData() {
        try {
            return await localforage.getItem('user_data');
        } catch (error) {
            console.error("[OfflineData] Erro ao recuperar dados do usuÃ¡rio:", error);
            return null;
        }
    }

    /**
     * Limpa todos os dados offline (Ãºtil no logout)
     */
    async function clearAllData() {
        try {
            await localforage.clear();
            console.log("[OfflineData] Todos os dados foram limpos");
            return true;
        } catch (error) {
            console.error("[OfflineData] Erro ao limpar dados:", error);
            return false;
        }
    }

    /**
     * PrÃ©-carrega dados essenciais quando usuÃ¡rio faz login
     */
    async function preloadEssentialData() {
        if (!navigator.onLine) {
            console.log("[OfflineData] Offline - nÃ£o Ã© possÃ­vel prÃ©-carregar dados");
            return false;
        }

        try {
            const token = await getAuthToken();
            if (!token) {
                console.warn("[OfflineData] Sem token para prÃ©-carregamento");
                return false;
            }

            const headers = {
                'Authorization': `Bearer ${token}`,
                'Accept': 'application/json'
            };

            // Buscar clientes
            try {
                const clientsResponse = await fetch('/api/clients', { headers });
                if (clientsResponse.ok) {
                    const clients = await clientsResponse.json();
                    await storeData('clients', clients.data || clients);
                    console.log('[OfflineData] Clientes prÃ©-carregados');
                }
            } catch (e) {
                console.warn('[OfflineData] Erro ao carregar clientes:', e);
            }

            // Buscar produtos
            try {
                const productsResponse = await fetch('/api/products', { headers });
                if (productsResponse.ok) {
                    const products = await productsResponse.json();
                    await storeData('products', products.data || products);
                    console.log('[OfflineData] Produtos prÃ©-carregados');
                }
            } catch (e) {
                console.warn('[OfflineData] Erro ao carregar produtos:', e);
            }

            // Buscar visitas de venda do usuÃ¡rio (Ãºltimos 30 dias)
            try {
                const visitsResponse = await fetch('/api/sales-visits?limit=100', { headers });
                if (visitsResponse.ok) {
                    const visits = await visitsResponse.json();
                    await storeData('sales_visits', visits.data || visits);
                    console.log('[OfflineData] Visitas prÃ©-carregadas');
                }
            } catch (e) {
                console.warn('[OfflineData] Erro ao carregar visitas:', e);
            }

            // Buscar pedidos de venda (Ãºltimos 30 dias)
            try {
                const ordersResponse = await fetch('/api/sales-orders?limit=100', { headers });
                if (ordersResponse.ok) {
                    const orders = await ordersResponse.json();
                    await storeData('sales_orders', orders.data || orders);
                    console.log('[OfflineData] Pedidos prÃ©-carregados');
                }
            } catch (e) {
                console.warn('[OfflineData] Erro ao carregar pedidos:', e);
            }

            return true;
        } catch (error) {
            console.error("[OfflineData] Erro no prÃ©-carregamento:", error);
            return false;
        }
    }

    // Expor API globalmente
    window.OfflineData = {
        storeData,
        retrieveData,
        upsertItem,
        deleteItem,
        addToSyncQueue,
        saveAuthToken,
        getAuthToken,
        saveUserData,
        getUserData,
        clearAllData,
        preloadEssentialData,
        DATA_STORES
    };

    console.log("[OfflineData] Inicializado com sucesso");

    // Auto-preload quando voltar online
    window.addEventListener('online', () => {
        console.log('[OfflineData] ConexÃ£o restaurada - iniciando prÃ©-carregamento');
        preloadEssentialData();
    });

})();