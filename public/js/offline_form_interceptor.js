(function() {
    'use strict';

    let interceptorInitialized = false;

    function initInterceptor() {
        if (interceptorInitialized) return;
        if (typeof Livewire === 'undefined' || typeof window.OfflineData === 'undefined') {
            setTimeout(initInterceptor, 250);
            return;
        }

        interceptorInitialized = true;
        console.log('[OfflineInterceptor] Inicializando...');

        // Interceptar requisição Livewire antes de serem enviadas
        Livewire.hook('commit', ({ component, commit, respond }) => {
            // Se estiver offline
            if (!navigator.onLine) {
                console.log('[OfflineInterceptor] Offline - interceptando commit:', component.name);
                
                // Verificar se é uma operação de criação/edição
                const updates = commit.updates || [];
                const calls = commit.calls || [];
                
                // Se há chamadas de método (como create, update)
                if (calls.length > 0) {
                    handleOfflineFormSubmit(component, commit, respond);
                    return false; // Cancelar envio
                }
            }
            
            // Online ou não é operação que precisa interceptar - continuar normal
            return true;
        });

        console.log('[OfflineInterceptor] Inicializado');
    }

    /**
     * Manipula submit de formulário offline
     */
    async function handleOfflineFormSubmit(component, commit, respond) {
        console.log('[OfflineInterceptor] Processando submit offline');
        
        try {
            // Extrair dados do componente
            const formData = component.canonical || component.data || {};
            
            // Determinar tipo de operação e store
            let storeName = null;
            let action = 'create';
            
            // Detectar baseado no nome do componente
            const componentName = component.name.toLowerCase();
            
            if (componentName.includes('client')) {
                storeName = 'clients';
            } else if (componentName.includes('product')) {
                storeName = 'products';
            } else if (componentName.includes('salesvisit') || componentName.includes('sales-visit')) {
                storeName = 'sales_visits';
            } else if (componentName.includes('salesorder') || componentName.includes('sales-order')) {
                storeName = 'sales_orders';
            }
            
            // Se é edição (tem ID ou UUID), mudar ação
            if (formData.id || formData.uuid) {
                action = 'update';
            }
            
            if (!storeName) {
                console.warn('[OfflineInterceptor] Store não identificado:', component.name);
                showOfflineError('Não foi possível salvar offline');
                return;
            }
            
            console.log('[OfflineInterceptor] Salvando:', { storeName, action, formData });
            
            // Adicionar company_id se não existir
            if (!formData.company_id && window.appCompanyId) {
                formData.company_id = window.appCompanyId;
            }
            
            // Adicionar à fila de sincronização
            await window.OfflineData.addToSyncQueue(storeName, action, formData);
            
            // Mostrar notificação de sucesso
            const actionLabel = action === 'create' ? 'criado' : 'atualizado';
            showOfflineSuccess(actionLabel);
            
            // Simular resposta Livewire para não quebrar a UI
            respond(() => {
                return {
                    effects: {
                        html: '',
                        dirty: []
                    },
                    serverMemo: component.serverMemo || {}
                };
            });
            
            // Redirecionar para lista após 1 segundo
            setTimeout(() => {
                // Tentar voltar usando Livewire navigate
                if (window.Livewire && Livewire.navigate) {
                    const url = new URL(window.location.href);
                    url.pathname = url.pathname.replace(/\/(create|edit).*/, '');
                    Livewire.navigate(url.toString());
                } else {
                    window.history.back();
                }
            }, 1000);
            
        } catch (error) {
            console.error('[OfflineInterceptor] Erro ao salvar offline:', error);
            showOfflineError('Erro ao salvar offline');
        }
    }

    /**
     * Mostra notificação de sucesso
     */
    function showOfflineSuccess(action) {
        // Tentar usar notificação Filament se disponí­vel
        if (window.FilamentNotification) {
            window.FilamentNotification.make()
                .title(`Registro ${action} offline`)
                .body('Será sincronizado automaticamente quando reconectar.')
                .success()
                .send();
        } else {
            // Fallback: notificação customizada
            showNotification(`Registro ${action} offline. Será sincronizado automaticamente.`, 'success');
        }
    }

    /**
     * Mostra notificação de erro
     */
    function showOfflineError(message) {
        if (window.FilamentNotification) {
            window.FilamentNotification.make()
                .title('Erro')
                .body(message)
                .danger()
                .send();
        } else {
            showNotification(`${message}`, 'error');
        }
    }

    /**
     * Notificação customizada simples
     */
    function showNotification(message, type = 'info') {
        const colors = {
            success: '#10b981',
            error: '#ef4444',
            info: '#3b82f6'
        };

        const notification = document.createElement('div');
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: ${colors[type]};
            color: white;
            padding: 16px 24px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            z-index: 99999;
            max-width: 400px;
            animation: slideInRight 0.3s ease;
        `;
        notification.textContent = message;

        document.body.appendChild(notification);

        setTimeout(() => {
            notification.style.animation = 'slideOutRight 0.3s ease';
            setTimeout(() => notification.remove(), 300);
        }, 4000);
    }

    // Adicionar CSS para animações
    if (!document.getElementById('offline-interceptor-styles')) {
        const style = document.createElement('style');
        style.id = 'offline-interceptor-styles';
        style.textContent = `
            @keyframes slideInRight {
                from {
                    transform: translateX(100%);
                    opacity: 0;
                }
                to {
                    transform: translateX(0);
                    opacity: 1;
                }
            }
            @keyframes slideOutRight {
                from {
                    transform: translateX(0);
                    opacity: 1;
                }
                to {
                    transform: translateX(100%);
                    opacity: 0;
                }
            }
        `;
        document.head.appendChild(style);
    }

    // Expor globalmente
    window.OfflineFormInterceptor = {
        init: initInterceptor
    };

    // Inicializar
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initInterceptor);
    } else {
        initInterceptor();
    }

})();