<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Relatório de Lançamentos Financeiros</title>
    <style>
        @page { margin: 10mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #333; }
        .header { text-align: center; margin-bottom: 16px; }
        .header h1 { margin: 0 0 5px; font-size: 19px; }
        .header p { margin: 2px 0; }
        h2 { margin: 16px 0 6px; padding: 5px 7px; font-size: 13px; color: #fff; }
        h2.income { background: #15803d; }
        h2.expense { background: #b91c1c; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th, td { border: 1px solid #d1d5db; padding: 6px; vertical-align: top; }
        th { background: #f3f4f6; text-align: left; }
        .account { width: 35%; }
        .description { width: 45%; }
        .amount { width: 20%; text-align: right; white-space: nowrap; }
        .subtotal td { background: #f9fafb; font-weight: bold; }
        .summary { margin-top: 18px; page-break-inside: avoid; }
        .summary td { font-size: 12px; font-weight: bold; }
        .positive { color: #15803d; }
        .negative { color: #b91c1c; }
        .empty { text-align: center; color: #6b7280; }
    </style>
    @include('pdf.partials.typography')
</head>
<body>
    @include('pdf.partials.system_footer')
    @include('pdf.partials.company_logo', ['company' => $company])

    <div class="header">
        <h1>Relatório de Lançamentos Financeiros</h1>
        <p><strong>Período:</strong> {{ \Carbon\Carbon::parse($startDate)->format('d/m/Y') }} a {{ \Carbon\Carbon::parse($endDate)->format('d/m/Y') }}</p>
        <p>Gerado em {{ now()->format('d/m/Y H:i') }}</p>
    </div>

    @php
        $sections = [
            ['title' => 'Entradas', 'class' => 'income', 'items' => $incomeTransactions, 'total' => $incomeTotal],
            ['title' => 'Saídas', 'class' => 'expense', 'items' => $expenseTransactions, 'total' => $expenseTotal],
        ];
    @endphp

    @foreach($sections as $section)
        <h2 class="{{ $section['class'] }}">{{ $section['title'] }}</h2>
        <table>
            <thead>
                <tr>
                    <th class="account">Plano de Conta</th>
                    <th class="description">Descrição</th>
                    <th class="amount">Valor</th>
                </tr>
            </thead>
            <tbody>
                @forelse($section['items'] as $transaction)
                    <tr>
                        <td>{{ $transaction->chartOfAccount?->code }}{{ $transaction->chartOfAccount?->code ? ' - ' : '' }}{{ $transaction->chartOfAccount?->name ?? 'Sem plano de conta' }}</td>
                        <td>{{ $transaction->description ?: '—' }}</td>
                        <td class="amount">R$ {{ number_format((float) $transaction->amount / 100, 2, ',', '.') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="empty">Nenhum lançamento no período.</td></tr>
                @endforelse
                <tr class="subtotal">
                    <td colspan="2">Total de {{ $section['title'] }}</td>
                    <td class="amount">R$ {{ number_format($section['total'], 2, ',', '.') }}</td>
                </tr>
            </tbody>
        </table>
    @endforeach

    <table class="summary">
        <tr>
            <td>Total de Entradas</td>
            <td class="amount positive">R$ {{ number_format($incomeTotal, 2, ',', '.') }}</td>
        </tr>
        <tr>
            <td>Total de Saídas</td>
            <td class="amount negative">R$ {{ number_format($expenseTotal, 2, ',', '.') }}</td>
        </tr>
        <tr>
            <td>Saldo Geral (Entradas − Saídas)</td>
            <td class="amount {{ $generalTotal < 0 ? 'negative' : 'positive' }}">R$ {{ number_format($generalTotal, 2, ',', '.') }}</td>
        </tr>
    </table>
</body>
</html>
