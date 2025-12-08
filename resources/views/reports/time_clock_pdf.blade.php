<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Relatório Consultor RH</title>

    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; }
        h1 { font-size: 22px; margin-bottom: 5px; }
        h2 { margin-top: 25px; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { border: 1px solid #444; padding: 6px; }
        th { background: #eee; }
        #logo { width: 120px; margin-bottom: 10px; }
    </style>
</head>
<body>
    <header>
        <div>
            <img src="images/logo-agostini-full_color-1-horizontal.png" alt="Agostini Tecnologia de Gestão" id="logo">
        </div>
    </header>

    <h1>Relatório Consultor RH</h1>
    <p><strong>Empresa:</strong> {{ $company->fantasy_name }}</p>
    <p><strong>Período:</strong> {{ $inicio }} até {{ $fim }}</p>
    <p><strong>Gerado em:</strong> {{ $generatedAt->format('d/m/Y H:i') }}</p>

    <h2>Batidas de Ponto</h2>
    <table>
        <thead>
            <tr>
                <th>Colaborador</th>
                <th>Data e Hora</th>
                <th>Ação</th>
                <th>Aprovador</th>
            </tr>
        </thead>
        <tbody>
            @foreach($entries as $e)
                <tr>
                    <td>{{ $e->user->name }}</td>
                    <td>{{ \Carbon\Carbon::parse($e->recorded_at)->format('d/m/Y H:i') }}</td>
                    <td>{{ strtoupper($e->action_type) }}</td>
                    <td>{{ $e->approver->name ?? '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h2>Análise Profissional (IA)</h2>
    <p>{!! nl2br(e($ia)) !!}</p>

</body>
</html>
