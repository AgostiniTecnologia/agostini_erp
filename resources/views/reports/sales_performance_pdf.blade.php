<!doctype html>

<html>
<head>
    <meta charset="utf-8">
    <title>Relatório Consultor de Vendas - {{ $generated_at->format('d/m/Y H:i') }}</title>
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 11px; color:#222; margin:15px; line-height: 1.4; }
        h1 { font-size: 18px; margin: 8px 0; color: #2c3e50; }
        h2 { font-size: 14px; margin: 12px 0 6px 0; color: #34495e; border-bottom: 2px solid #3498db; padding-bottom: 3px; }
        h3 { font-size: 12px; margin: 10px 0 4px 0; color: #7f8c8d; }
        table { width:100%; border-collapse: collapse; margin-bottom: 10px; font-size: 10px; }
        th, td { border:1px solid #ddd; padding:6px; text-align:left; }
        th { background:#f4f4f4; font-weight:600; }
        .section { margin-bottom: 18px; page-break-inside: avoid; }
        .small { font-size:10px; color:#555; }
        .analysis { background:#f8f9fa; padding:12px; border-left:4px solid #28a745; white-space: pre-wrap; font-size: 10px; margin: 10px 0; }
        .muted { color:#666; font-size:10px; }
        .summary-box { background: #e3f2fd; padding: 10px; border-radius: 4px; margin: 10px 0; }
        #logo { width: 120px; margin-bottom: 10px; }
        .abc-a { color: #28a745; font-weight: bold; }
        .abc-b { color: #ffc107; font-weight: bold; }
        .abc-c { color: #dc3545; font-weight: bold; }
    </style>
</head>
<body>
    <header>
        <div>
            <img src="images/logo-agostini-full_color-1-horizontal.png" alt="Agostini Tecnologia de Gestão" id="logo">
        </div>
    </header>

    <h1>🎯 Relatório Consultor Inteligente de Vendas</h1>
    <div class="muted">Gerado em: {{ $generated_at->format('d/m/Y H:i') }} | Período: {{ $startDate }} até {{ $endDate }}</div>

    <div class="section summary-box">
        <h2>📊 Resumo Executivo</h2>
        <table>
            <tr>
                <th>Vendas Totais</th>
                <th>Meta Total</th>
                <th>Taxa de Atingimento</th>
                <th>Comissões Totais</th>
                <th>Conv. Visitas</th>
            </tr>
            <tr>
                <td><strong>R$ {{ number_format($reportData['summary']['total_sales'], 2, ',', '.') }}</strong></td>
                <td>R$ {{ number_format($reportData['summary']['total_goal'], 2, ',', '.') }}</td>
                <td class="{{ $reportData['summary']['achievement_rate'] >= 100 ? 'abc-a' : ($reportData['summary']['achievement_rate'] >= 80 ? 'abc-b' : 'abc-c') }}">
                    {{ number_format($reportData['summary']['achievement_rate'], 2, ',', '.') }}%
                </td>
                <td>R$ {{ number_format($reportData['summary']['total_commission'], 2, ',', '.') }}</td>
                <td>{{ number_format($reportData['summary']['avg_conversion_rate'], 2, ',', '.') }}%</td>
            </tr>
        </table>
    </div>

    @foreach($reportData['rows'] as $salesperson)

    <div class="section">
        <h2>{{ $salesperson['salesperson'] }}</h2>

    <table>
        <tr>
            <th>Vendas</th>
            <th>Meta</th>
            <th>Performance</th>
            <th>Comissão</th>
            <th>Visitas</th>
            <th>Taxa Conv.</th>
        </tr>
        <tr>
            <td>R$ {{ number_format($salesperson['totals']['sales'], 2, ',', '.') }}</td>
            <td>R$ {{ number_format($salesperson['totals']['goal'], 2, ',', '.') }}</td>
            <td class="{{ $salesperson['totals']['performance'] >= 100 ? 'abc-a' : ($salesperson['totals']['performance'] >= 80 ? 'abc-b' : 'abc-c') }}">
                {{ number_format($salesperson['totals']['performance'], 2, ',', '.') }}%
            </td>
            <td>R$ {{ number_format($salesperson['totals']['commission'], 2, ',', '.') }}</td>
            <td>{{ $salesperson['visits']['completed'] }}</td>
            <td>{{ number_format($salesperson['visits']['conversion_rate'], 2, ',', '.') }}%</td>
        </tr>
    </table>

    @if(!empty($salesperson['abc_clients']))
    <h3>📊 Curva ABC - Clientes</h3>
    <table>
        <tr>
            <th>Cliente</th>
            <th>Valor</th>
            <th>% Acumulada</th>
            <th>Categoria</th>
        </tr>
        @foreach($salesperson['abc_clients'] as $client)
        <tr>
            <td>{{ $client['client'] }}</td>
            <td>R$ {{ number_format($client['total'], 2, ',', '.') }}</td>
            <td>{{ number_format($client['accumulated_percentage'], 2, ',', '.') }}%</td>
            <td class="abc-{{ strtolower($client['category']) }}">{{ $client['category'] }}</td>
        </tr>
        @endforeach
    </table>
    @endif

    @if(!empty($salesperson['abc_products']))
    <h3>📦 Curva ABC - Produtos</h3>
    <table>
        <tr>
            <th>Produto</th>
            <th>Valor</th>
            <th>% Acumulada</th>
            <th>Categoria</th>
        </tr>
        @foreach($salesperson['abc_products'] as $product)
        <tr>
            <td>{{ $product['product'] }}</td>
            <td>R$ {{ number_format($product['total'], 2, ',', '.') }}</td>
            <td>{{ number_format($product['accumulated_percentage'], 2, ',', '.') }}%</td>
            <td class="abc-{{ strtolower($product['category']) }}">{{ $product['category'] }}</td>
        </tr>
        @endforeach
    </table>
    @endif

    @if($salesperson['visits']['without_order'] > 0)
    <h3>⚠️ Visitas Sem Pedido</h3>
    <table>
        <tr>
            <th>Cliente</th>
            <th>Data</th>
            <th>Motivo</th>
        </tr>
        @foreach($salesperson['visits']['without_order_details'] as $visit)
        <tr>
            <td>{{ $visit['client'] }}</td>
            <td>{{ $visit['date'] }}</td>
            <td class="small">{{ $visit['reason'] }}</td>
        </tr>
        @endforeach
    </table>
    @endif

    </div>
    @endforeach

    <div class="section">
        <h2>🤖 Análise Inteligente do Consultor IA</h2>
        <div class="analysis">
            {!! nl2br(e($reportData['analysis'])) !!}
        </div>
    </div>

    <div class="small muted" style="margin-top: 20px; padding-top: 10px; border-top: 1px solid #ddd;">
        Relatório gerado automaticamente pelo Sistema de Gestão com IA | Agostini Tecnologia
    </div>
</body>
</html>
