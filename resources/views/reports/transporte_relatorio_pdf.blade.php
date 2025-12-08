<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Relatório de Transporte</title>

    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; }
        h1 { text-align: center; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { border: 1px solid #555; padding: 6px; }
        th { background: #eee; }
        .titulo { background: #ddd; font-weight: bold; }
        .secao { margin-top: 25px; font-size: 15px; font-weight: bold; }
        #logo{width: 150px;}
    </style>
</head>
<body>
     <header>
        <div>
            <img src="images/logo-agostini-full_color-1-horizontal.png" alt="Agostini Tecnologia de Gestão" id="logo">
        </div>
    </header>

    <h1>Relatório de Transporte</h1>
    <p><strong>Período:</strong>
        {{ \Carbon\Carbon::parse($inicio)->format('d/m/Y') }}
        a
        {{ \Carbon\Carbon::parse($fim)->format('d/m/Y') }}
    </p>

    <div class="secao">Resumo Geral</div>

    <table>
        <tr><th>Total Itens Transportados</th><td>{{ $totalItens }}</td></tr>
        <tr><th>Entregas Concluídas</th><td>{{ $totalEntregasFeitas }}</td></tr>
        <tr><th>Itens Retornados</th><td>{{ $totalRetornados }}</td></tr>
    </table>

    @foreach ($transportes as $t)
        <div class="secao">Transporte #{{ $t->transport_order_number }}</div>

        <table>
            <tr>
                <th>Status</th>
                <td>{{ ucfirst(str_replace('_',' ', $t->status)) }}</td>
            </tr>

            <tr>
                <th>Veículo</th>
                <td>{{ $t->vehicle->plate ?? '—' }}</td>
            </tr>

            <tr>
                <th>Motorista</th>
                <td>{{ $t->driver->name ?? '—' }}</td>
            </tr>

            <tr>
                <th>Saída Planejada</th>
                <td>{{ optional($t->planned_departure_datetime)->format('d/m/Y H:i') ?? '—' }}</td>
            </tr>

            <tr>
                <th>Chegada Planejada</th>
                <td>{{ optional($t->planned_arrival_datetime)->format('d/m/Y H:i') ?? '—' }}</td>
            </tr>
        </table>

        <br>

        <table>
            <tr class="titulo">
                <th>Cliente</th>
                <th>Produto</th>
                <th>Quantidade</th>
                <th>Status</th>
                <th>Entregue Em</th>
                <th>Retornado Em</th>
            </tr>

            @foreach ($t->items as $item)
                <tr>
                    <td>{{ $item->client->name ?? '—' }}</td>
                    <td>{{ $item->product->name ?? '—' }}</td>
                    <td>{{ $item->quantity }}</td>

                    <td>
                        @switch($item->status)
                            @case('completed') Concluído @break
                            @case('returned') Retornado @break
                            @default Pendente
                        @endswitch
                    </td>

                    <td>{{ optional($item->delivered_at)->format('d/m/Y H:i') ?? '—' }}</td>
                    <td>{{ optional($item->returned_at)->format('d/m/Y H:i') ?? '—' }}</td>
                </tr>
            @endforeach
        </table>

        <hr>
    @endforeach

</body>
</html>
