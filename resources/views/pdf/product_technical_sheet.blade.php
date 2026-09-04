<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Ficha técnica - {{ $product->name }}</title>
    <style>
        @page { margin: 24px; }
        body { color: #1f2937; font-family: DejaVu Sans, sans-serif; font-size: 10px; }
        h1 { font-size: 18px; margin: 0; text-align: center; }
        h2 { background: #e5e7eb; border: 1px solid #9ca3af; font-size: 12px; margin: 16px 0 0; padding: 6px; }
        .subtitle { color: #4b5563; margin: 4px 0 16px; text-align: center; }
        table { border-collapse: collapse; margin: 0; width: 100%; }
        th, td { border: 1px solid #9ca3af; padding: 6px; text-align: left; vertical-align: top; }
        th { background: #f3f4f6; font-weight: bold; }
        .label { background: #f9fafb; font-weight: bold; width: 22%; }
        .number { text-align: right; }
        .result { background: #ecfeff; font-size: 14px; font-weight: bold; text-align: center; }
        .footer { color: #6b7280; font-size: 8px; margin-top: 20px; text-align: right; }
    </style>
</head>
<body>
    @include('pdf.partials.system_footer')
    @include('pdf.partials.company_logo', ['company' => $product->company])
    @php
        $measurements = $product->cardboard_measurements ?? [];
        $lengthUnit = $product->company?->length_unit?->value ?? 'm';
        $weightUnit = $product->company?->weight_unit?->value ?? 'kg';
        $value = fn ($field, $suffix = '') => filled(data_get($product, $field)) ? data_get($product, $field).$suffix : 'Não informado';
        $measurement = fn ($field) => filled($measurements[$field] ?? null) ? str_replace('.', ',', $measurements[$field]).' '.$lengthUnit : 'Não informado';
        $money = fn ($field) => filled($product->{$field}) ? 'R$ '.number_format((float) $product->{$field}, 2, ',', '.') : 'Não informado';
    @endphp

    <h1>Ficha técnica do produto</h1>
    <div class="subtitle">{{ $product->company->name ?? 'Empresa não informada' }} · Emitida em {{ now()->format('d/m/Y H:i') }}</div>

    <h2>Identificação</h2>
    <table>
        <tr><td class="label">Nome</td><td>{{ $product->name }}</td><td class="label">SKU</td><td>{{ $value('sku') }}</td></tr>
        <tr><td class="label">Unidade de medida</td><td>{{ $value('unit_of_measure') }}</td><td class="label">Estoque</td><td>{{ $value('stock') }}</td></tr>
        <tr><td class="label">Descrição</td><td colspan="3">{{ $value('description') }}</td></tr>
    </table>

    <h2>Custos e preços</h2>
    <table>
        <tr><td class="label">Custo padrão</td><td>{{ $money('standard_cost') }}</td></tr>
        <tr><td class="label">Preço de venda</td><td>{{ $money('sale_price') }}</td></tr>
        <tr><td class="label">Preço mínimo de venda</td><td>{{ $money('minimum_sale_price') }}</td></tr>
    </table>

    @if(($product->company?->getRawOriginal('operational_profile') ?? 'standard') === 'cardboard_packaging')
        <h2>Medidas internas da embalagem</h2>
        <table>
            <tr><th>Comprimento interno</th><th>Largura interna</th><th>Altura interna</th></tr>
            <tr><td>{{ $measurement('internal_length') }}</td><td>{{ $measurement('internal_width') }}</td><td>{{ $measurement('internal_height') }}</td></tr>
        </table>

        <h2>Composição do comprimento da chapa</h2>
        <table>
            <tr><th>Aba esquerda</th><th>Altura esquerda</th><th>Comprimento</th><th>Altura direita</th><th>Aba direita</th><th>Total</th></tr>
            <tr>
                <td>{{ $measurement('left_flap') }}</td><td>{{ $measurement('left_height') }}</td><td>{{ $measurement('sheet_length') }}</td>
                <td>{{ $measurement('right_height') }}</td><td>{{ $measurement('right_flap') }}</td><td>{{ \App\Support\CardboardMeasurements::format($lengthTotal) }} {{ $lengthUnit }}</td>
            </tr>
        </table>

        <h2>Composição da largura da chapa</h2>
        <table>
            <tr><th>Aba superior</th><th>Altura superior</th><th>Largura</th><th>Altura inferior</th><th>Aba inferior</th><th>Total</th></tr>
            <tr>
                <td>{{ $measurement('top_flap') }}</td><td>{{ $measurement('top_height') }}</td><td>{{ $measurement('sheet_width') }}</td>
                <td>{{ $measurement('bottom_height') }}</td><td>{{ $measurement('bottom_flap') }}</td><td>{{ \App\Support\CardboardMeasurements::format($widthTotal) }} {{ $lengthUnit }}</td>
            </tr>
        </table>
        <table><tr><td class="result">Tamanho da chapa: {{ \App\Support\CardboardMeasurements::format($lengthTotal) }} × {{ \App\Support\CardboardMeasurements::format($widthTotal) }} {{ $lengthUnit }}</td></tr></table>
    @else
        <h2>Medidas e peso</h2>
        <table>
            <tr><td class="label">Peso líquido</td><td>{{ $value('weight_net', ' '.$weightUnit) }}</td><td class="label">Peso bruto</td><td>{{ $value('weight', ' '.$weightUnit) }}</td></tr>
            <tr><td class="label">Comprimento</td><td>{{ $value('length', ' '.$lengthUnit) }}</td><td class="label">Largura</td><td>{{ $value('width', ' '.$lengthUnit) }}</td></tr>
            <tr><td class="label">Altura</td><td colspan="3">{{ $value('height', ' '.$lengthUnit) }}</td></tr>
        </table>
    @endif

    <h2>Matérias-primas</h2>
    <table>
        <tr><th>Nome</th><th>SKU</th><th>Quantidade</th><th>Unidade de medida</th></tr>
        @forelse($product->rawMaterials as $rawMaterial)
            <tr><td>{{ $rawMaterial->name }}</td><td>{{ $rawMaterial->sku ?: 'Não informado' }}</td><td>{{ $rawMaterial->pivot->quantity }}</td><td>{{ $rawMaterial->pivot->unit_of_measure ?: $rawMaterial->unit_of_measure }}</td></tr>
        @empty
            <tr><td colspan="4">Nenhuma matéria-prima vinculada.</td></tr>
        @endforelse
    </table>

    <h2>Etapas de produção</h2>
    <table>
        <tr><th>Ordem</th><th>Etapa</th><th>Descrição</th></tr>
        @forelse($product->productionSteps as $step)
            <tr><td>{{ $step->pivot->step_order }}</td><td>{{ $step->name }}</td><td>{{ $step->description ?: 'Não informada' }}</td></tr>
        @empty
            <tr><td colspan="3">Nenhuma etapa de produção vinculada.</td></tr>
        @endforelse
    </table>

    <div class="footer">Produto {{ $product->uuid }}</div>
</body>
</html>
