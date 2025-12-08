<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Relatório Financeiro</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { border: 1px solid #333; padding: 5px; text-align: right; }
        th { background-color: #f2f2f2; }
        td.left { text-align: left; }
        h2, h3 { margin-bottom: 5px; }
        .summary { margin-top: 20px; }
        #logo { width: 120px; margin-bottom: 10px; }

    </style>
</head>
<body>
     <header>
        <div>
            <img src="images/logo-agostini-full_color-1-horizontal.png" alt="Agostini Tecnologia de Gestão" id="logo">
        </div>
    </header>
    <h2>Relatório Financeiro</h2>
    <p>Período: {{ \Carbon\Carbon::parse($startDate)->format('d/m/Y') }} até {{ \Carbon\Carbon::parse($endDate)->format('d/m/Y') }}</p>

    <h3>Resumo Geral</h3>
    <table>
        <tr>
            <th class="left">Descrição</th>
            <th>Receita (R$)</th>
            <th>Despesa (R$)</th>
            <th>Lucro Líquido (R$)</th>
        </tr>
        <tr>
            <td class="left">Total do Período</td>
            <td>{{ number_format($reportData['summary']['revenue'], 2, ',', '.') }}</td>
            <td>{{ number_format($reportData['summary']['expense'], 2, ',', '.') }}</td>
            <td>{{ number_format($reportData['summary']['net_profit'], 2, ',', '.') }}</td>
        </tr>
    </table>

    <h3>Análise Mensal</h3>
    <table>
        <tr>
            <th class="left">Mês</th>
            <th>Receita (R$)</th>
            <th>Despesa (R$)</th>
            <th>Lucro Líquido (R$)</th>
            <th>Observação</th>
        </tr>
        @foreach($reportData['months'] as $month)
            <tr>
                <td class="left">{{ $month['month'] }}</td>
                <td>{{ number_format($month['revenue'], 2, ',', '.') }}</td>
                <td>{{ number_format($month['expense'], 2, ',', '.') }}</td>
                <td>{{ number_format($month['net_profit'], 2, ',', '.') }}</td>
                <td class="left">
                    @if($month['net_profit'] > 0)
                        Mês positivo
                    @elseif($month['net_profit'] < 0)
                        Mês negativo
                    @else
                        Equilibrado
                    @endif
                </td>
            </tr>
        @endforeach
    </table>
</body>
</html>
