<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Relatório de Produção - {{ $generated_at->format('Y-m-d H:i') }}</title>
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 12px; color:#222; margin:20px; }
        h1,h2,h3 { margin: 8px 0; }
        table { width:100%; border-collapse: collapse; margin-bottom: 12px; }
        th, td { border:1px solid #ddd; padding:8px; text-align:left; }
        th { background:#f4f4f4; font-weight:600; }
        .section { margin-bottom: 20px; page-break-inside: avoid; }
        .small { font-size:11px; color:#555; }
        .chart { text-align:center; margin: 12px 0; }
        .analysis { background:#fbfbfb; padding:12px; border-left:4px solid #007bff; white-space: pre-wrap; }
        .muted { color:#666; font-size:11px; }
    </style>
</head>
<body>
    <h1>Relatório Inteligente de Produção</h1>
    <div class="muted">Gerado em: {{ $generated_at->format('Y-m-d H:i') }}</div>

    <div class="section">
        <h2>Indicadores Gerais</h2>
        <table>
            <thead>
                <tr>
                    <th>Total de Ordens</th>
                    <th>Tempo Médio por Ordem (s)</th>
                    <th>Produtos com Ordens</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>{{ $dashboard['total_orders'] ?? 0 }}</td>
                    <td>{{ $dashboard['avg_lead_time_seconds'] ?? '-' }}</td>
                    <td>{{ count($rows) }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="section">
        <h2>Gráficos</h2>

        <h3>Ranking - Top produtos (tempo médio)</h3>
        <div class="chart">
            @if(!empty($images['bar']))
                <img src="data:image/png;base64,{{ $images['bar'] }}" 
                     alt="Gráfico de Barras"
                     style="max-width:100%; height:auto;">
            @else
                <div class="small">Sem dados para gráfico.</div>
            @endif
        </div>

        <h3>Histórico (últimas ordens) + Regressão</h3>
        <div class="chart">
            @if(!empty($images['line']))
                <img src="data:image/png;base64,{{ $images['line'] }}" 
                     alt="Gráfico de Linha"
                     style="max-width:100%; height:auto;">
            @else
                <div class="small">Sem dados para gráfico.</div>
            @endif
        </div>
    </div>

    <div class="section">
        <h2>Ranking dos Produtos Mais Lentos</h2>
        <table>
            <thead>
                <tr>
                    <th>Produto</th>
                    <th>Tempo Efetivo Médio (s)</th>
                    <th>Tempo Morto Médio (s)</th>
                    <th>Ordens Concluídas</th>
                </tr>
            </thead>
            <tbody>
                @foreach($rows as $r)
                <tr>
                    <td>{{ $r['product'] ?? ($r['product_name'] ?? '—') }}</td>
                    <td>{{ $r['avg_effective_seconds'] ?? 0 }}</td>
                    <td>{{ $r['avg_dead_seconds'] ?? 0 }}</td>
                    <td>{{ $r['count'] ?? 0 }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="section">
        <h2>Motivos de Pausa</h2>
        <table>
            <thead>
                <tr>
                    <th>Motivo</th>
                    <th>Tipo</th>
                    <th>Total (segundos)</th>
                </tr>
            </thead>
            <tbody>
                @forelse($pause_reasons as $p)
                <tr>
                    <td>{{ $p['motivo'] ?? '—' }}</td>
                    <td>{{ $p['tipo'] ?? '—' }}</td>
                    <td>{{ $p['total_seconds'] ?? 0 }}</td>
                </tr>
                @empty
                <tr><td colspan="3">Nenhum registro de pausa encontrado.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="section">
        <h2>Histórico de Produção (últimas ordens)</h2>
        <table>
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Produto</th>
                    <th>Lead Time (s)</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($history as $h)
                <tr>
                    <td>{{ $h['date'] ?? '—' }}</td>
                    <td>{{ $h['product'] ?? '—' }}</td>
                    <td>{{ $h['lead_time_seconds'] ?? 0 }}</td>
                    <td>{{ $h['status'] ?? '—' }}</td>
                </tr>
                @empty
                <tr><td colspan="4">Nenhum histórico encontrado.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="section">
        <h2>Análise Automatizada (IA)</h2>
        <div class="analysis">
            {!! nl2br(e($analysis)) !!}
        </div>
    </div>

    <div class="small muted">
        Relatório gerado automaticamente pelo sistema.  
        Para dúvidas, verifique logs em <code>storage/logs/laravel.log</code>.
    </div>
</body>
</html>
