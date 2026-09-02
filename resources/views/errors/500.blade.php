@php
    $error = app(\App\Support\UserFacingError::class)->summarize($exception, request());
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $error['title'] }}</title>
    <style>
        :root { color-scheme: light dark; font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
        * { box-sizing: border-box; }
        html, body { background: transparent !important; }
        body { margin: 0; min-height: 100vh; color: #111827; }
        .backdrop { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .dialog { position: relative; width: 100%; max-width: 440px; overflow: hidden; background: #fff; border-radius: 16px; box-shadow: 0 24px 60px rgba(0, 0, 0, .28); }
        .content { padding: 28px 28px 20px; text-align: center; }
        .icon { display: grid; place-items: center; width: 52px; height: 52px; margin: 0 auto 16px; border-radius: 50%; color: #b42318; background: #fee4e2; font-size: 28px; font-weight: 700; }
        h1 { margin: 0; font-size: 20px; line-height: 1.35; }
        .message { margin: 10px 0 0; color: #4b5563; font-size: 14px; line-height: 1.55; }
        .cause { margin: 16px 0 0; padding: 12px 14px; border-left: 4px solid #dc2626; border-radius: 6px; background: #fef2f2; color: #7f1d1d; font-size: 14px; line-height: 1.5; text-align: left; }
        .cause strong { display: block; margin-bottom: 3px; }
        .close { position: absolute; top: 12px; right: 12px; width: 36px; height: 36px; border: 0; border-radius: 8px; background: transparent; color: #6b7280; cursor: pointer; font-size: 24px; line-height: 1; }
        .close:hover, .close:focus-visible { background: #f3f4f6; color: #111827; outline: none; }
        .details { display: none; margin: 18px 0 0; padding: 14px; border: 1px solid #e5e7eb; border-radius: 10px; background: #f9fafb; text-align: left; font-size: 13px; }
        .details.visible { display: block; }
        .details dl { display: grid; grid-template-columns: 92px 1fr; gap: 8px; margin: 0; }
        .details dt { color: #6b7280; }
        .details dd { margin: 0; overflow-wrap: anywhere; color: #1f2937; }
        .guidance { margin: 12px 0 0; color: #4b5563; line-height: 1.45; }
        .actions { display: flex; justify-content: flex-end; gap: 10px; padding: 16px 20px; border-top: 1px solid #e5e7eb; background: #f9fafb; }
        button { min-height: 40px; padding: 9px 16px; border-radius: 9px; font: inherit; font-size: 14px; font-weight: 600; cursor: pointer; }
        .secondary { border: 1px solid #d1d5db; background: #fff; color: #374151; }
        .primary { border: 1px solid #2563eb; background: #2563eb; color: #fff; }
        button:hover { filter: brightness(.96); }
        button:focus-visible { outline: 3px solid rgba(37, 99, 235, .3); outline-offset: 2px; }
        @media (prefers-color-scheme: dark) {
            body { color: #f9fafb; }
            .dialog { background: #111827; }
            .message, .guidance { color: #d1d5db; }
            .cause { border-color: #f87171; background: #450a0a; color: #fecaca; }
            .close { color: #9ca3af; }
            .close:hover, .close:focus-visible, .actions, .details { background: #1f2937; color: #f9fafb; }
            .actions, .details { border-color: #374151; }
            .details dt { color: #9ca3af; }
            .details dd { color: #f3f4f6; }
            .secondary { border-color: #4b5563; background: #1f2937; color: #f9fafb; }
        }
    </style>
</head>
<body>
    <main class="backdrop" role="main" onclick="if (event.target === this) closeErrorDialog()">
        <section class="dialog" role="alertdialog" aria-modal="true" aria-labelledby="error-title" aria-describedby="error-message">
            <button type="button" class="close" aria-label="Fechar" onclick="closeErrorDialog()">&times;</button>
            <div class="content">
                <div class="icon" aria-hidden="true">!</div>
                <h1 id="error-title">{{ $error['title'] }}</h1>
                <p id="error-message" class="message">{{ $error['message'] }}</p>
                <p class="cause"><strong>O que ocasionou o erro:</strong>{{ $error['cause'] }}</p>
                <div id="server-details" class="details" aria-live="polite">
                    <dl>
                        <dt>Categoria</dt><dd>{{ $error['category'] }}</dd>
                        <dt>Código</dt><dd>{{ $error['status'] }}</dd>
                        <dt>Horário</dt><dd>{{ $error['occurred_at'] }}</dd>
                        <dt>Página</dt><dd>{{ $error['path'] }}</dd>
                    </dl>
                    <p class="guidance"><strong>Como resolver:</strong><br>{{ $error['guidance'] }}</p>
                </div>
            </div>
            <div class="actions">
                <button type="button" id="consult-server" class="secondary" aria-expanded="false" aria-controls="server-details" onclick="toggleServerDetails(this)">Consultar servidor</button>
                <button type="button" class="primary" onclick="closeErrorDialog()">OK</button>
            </div>
        </section>
    </main>
    <script>
        function makeLivewireBackgroundTransparent() {
            if (window.parent === window) return;

            const livewireModal = window.parent.document.getElementById('livewire-error');

            if (livewireModal) {
                livewireModal.style.setProperty('background', 'transparent', 'important');
                livewireModal.style.padding = '0';
            }

            if (window.frameElement) {
                window.frameElement.setAttribute('allowtransparency', 'true');
                window.frameElement.style.setProperty('background', 'transparent', 'important');
                window.frameElement.style.borderRadius = '0';
                window.frameElement.style.border = '0';
            }
        }

        function toggleServerDetails(button) {
            const details = document.getElementById('server-details');
            const isVisible = details.classList.toggle('visible');
            button.setAttribute('aria-expanded', String(isVisible));
            button.textContent = isVisible ? 'Ocultar detalhes' : 'Consultar servidor';
        }

        function closeErrorDialog() {
            if (window.parent !== window) {
                const livewireModal = window.parent.document.getElementById('livewire-error');

                if (livewireModal) {
                    livewireModal.remove();
                    window.parent.document.body.style.overflow = '';
                    return;
                }
            }

            if (window.history.length > 1) return window.history.back();
            window.location.assign('/');
        }

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') closeErrorDialog();
        });

        makeLivewireBackgroundTransparent();
    </script>
</body>
</html>
