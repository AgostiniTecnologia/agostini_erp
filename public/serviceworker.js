const CACHE_VERSION = 'v6';
const STATIC_CACHE = `agostini-static-${CACHE_VERSION}`;
const DYNAMIC_CACHE = `agostini-dynamic-${CACHE_VERSION}`;
const HTML_CACHE = `agostini-html-${CACHE_VERSION}`;

// IMPORTANTE: Filament usa /app como raiz do painel
const PANEL_BASE = '/app';

// Assets essenciais do painel
const CRITICAL_ASSETS = [
    `${PANEL_BASE}`,
    `${PANEL_BASE}/home-page`,
    '/offline',
    '/manifest.json',
    '/images/icons/icon-192x192.png',
    '/images/icons/icon-512x512.png'
];

const CACHE_PATTERNS = {
    fonts: /\.(woff2?|ttf|eot)$/,
    images: /\.(png|jpg|jpeg|svg|gif|webp|ico)$/,
    styles: /\.css$/,
    scripts: /\.js$/,
    filament: /\/filament\//,
};

const EXCLUDED_PATTERNS = [
    /\/_debugbar/,
    /\/telescope/,
];

// INSTALL
self.addEventListener('install', (event) => {
    console.log('[SW] Instalando versão:', CACHE_VERSION);

    event.waitUntil(
        caches.open(STATIC_CACHE).then(async (cache) => {
            for (const asset of CRITICAL_ASSETS) {
                try {
                    await cache.add(asset);
                } catch (e) {
                    console.warn('[SW] Falha ao cachear asset crítico:', asset);
                }
            }
        })
    );

    self.skipWaiting();
});

// ACTIVATE
self.addEventListener('activate', (event) => {
    console.log('[SW] Ativando versão:', CACHE_VERSION);

    event.waitUntil(
        caches.keys().then(keys =>
            Promise.all(
                keys
                    .filter(k => k.startsWith('agostini-') && !k.includes(CACHE_VERSION))
                    .map(k => caches.delete(k))
            )
        )
    );

    self.clients.claim();
});

// FETCH
self.addEventListener('fetch', (event) => {
    const { request } = event;

    let url;
    try {
        url = new URL(request.url);
    } catch {
        return;
    }

    if (!url.protocol.startsWith('http')) return;

    if (EXCLUDED_PATTERNS.some(p => p.test(url.pathname))) return;

    // Navegação SPA do Filament
    if (request.mode === 'navigate') {
        event.respondWith(handleNavigation(request));
        return;
    }

    // Livewire
    if (url.pathname.includes('/livewire/') || url.pathname.includes('/_livewire/')) {
        event.respondWith(handleLivewire(request));
        return;
    }

    // API
    if (url.pathname.startsWith('/api/')) {
        event.respondWith(handleAPI(request));
        return;
    }

    // Fontes (cache-first)
    if (CACHE_PATTERNS.fonts.test(url.pathname)) {
        event.respondWith(cacheFirst(request, STATIC_CACHE));
        return;
    }

    // Imagens
    if (CACHE_PATTERNS.images.test(url.pathname)) {
        event.respondWith(cacheFirst(request, DYNAMIC_CACHE));
        return;
    }

    // Filament CSS/JS
    if (CACHE_PATTERNS.filament.test(url.pathname) &&
        (CACHE_PATTERNS.styles.test(url.pathname) || CACHE_PATTERNS.scripts.test(url.pathname))) {
        event.respondWith(cacheFirst(request, DYNAMIC_CACHE));
        return;
    }

    // Outros JS/CSS
    if (CACHE_PATTERNS.styles.test(url.pathname) || CACHE_PATTERNS.scripts.test(url.pathname)) {
        event.respondWith(cacheFirst(request, DYNAMIC_CACHE));
        return;
    }

    // Default: network first
    event.respondWith(networkFirst(request));
});

// Navegação SPA
async function handleNavigation(request) {
    try {
        const response = await fetch(request);

        if (response.ok) {
            const cache = await caches.open(HTML_CACHE);
            cache.put(request, response.clone());
        }

        return response;
    } catch {
        const cached = await caches.match(request);
        if (cached) return cached;

        const home = await caches.match(`${PANEL_BASE}/home-page`);
        if (home) return home;

        const panel = await caches.match(PANEL_BASE);
        if (panel) return panel;

        const offline = await caches.match('/offline');
        if (offline) return offline;

        return new Response('<h1>Offline</h1>', { headers: { 'Content-Type': 'text/html' } });
    }
}

// Livewire
async function handleLivewire(request) {
    try {
        return await fetch(request);
    } catch {
        if (request.method !== 'GET') {
            return new Response(
                JSON.stringify({
                    offline: true,
                    success: true,
                    message: 'Operação offline será sincronizada',
                    effects: { html: '', dirty: [] },
                    serverMemo: {}
                }),
                {
                    status: 200,
                    headers: { 'Content-Type': 'application/json' }
                }
            );
        }

        return new Response(JSON.stringify({ offline: true }), {
            status: 503,
            headers: { 'Content-Type': 'application/json' }
        });
    }
}

// API
async function handleAPI(request) {
    try {
        const response = await fetch(request);

        if (response.ok && request.method === 'GET') {
            const cache = await caches.open(DYNAMIC_CACHE);
            cache.put(request, response.clone());
        }

        return response;
    } catch {
        if (request.method === 'GET') {
            const cached = await caches.match(request);
            if (cached) return cached;
        }

        return new Response(JSON.stringify({ offline: true }), {
            status: 503,
            headers: { 'Content-Type': 'application/json' }
        });
    }
}

async function cacheFirst(request, cacheName) {
    const cached = await caches.match(request);
    if (cached) return cached;

    try {
        const response = await fetch(request);
        if (response.ok) {
            const cache = await caches.open(cacheName);
            cache.put(request, response.clone());
        }
        return response;
    } catch {
        return new Response('', { status: 503 });
    }
}

async function networkFirst(request) {
    try {
        const response = await fetch(request);
        if (response.ok) {
            const cache = await caches.open(DYNAMIC_CACHE);
            cache.put(request, response.clone());
        }
        return response;
    } catch {
        const cached = await caches.match(request);
        if (cached) return cached;

        return new Response('', { status: 503 });
    }
}

self.addEventListener('message', (event) => {
    if (event.data?.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }

    if (event.data?.type === 'CLEAR_CACHE') {
        event.waitUntil(
            caches.keys().then(names =>
                Promise.all(names.map(n => caches.delete(n)))
            )
        );
    }
});

console.log('[SW] Service Worker carregado:', CACHE_VERSION);
