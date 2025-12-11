<script>
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register("{{ asset('serviceworker.js') }}", {
            scope: '/app/'
        }).then(function (registration) {
            console.log('PWA: ServiceWorker registrado com sucesso:', registration.scope);
        }).catch(function (err) {
            console.log('PWA: ServiceWorker falhou ao registrar:', err);
        });
    }
</script>
