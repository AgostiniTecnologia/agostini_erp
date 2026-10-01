<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Ficha técnica - {{ $product->name }}</title>
    <style>
        @page { margin: 24px; }
        body { color: #1f2937; font-family: DejaVu Sans, sans-serif; font-size: 12px; }
        h1 { font-size: 20px; margin: 0; text-align: center; }
        h2 { background: #e5e7eb; border: 1px solid #9ca3af; font-size: 14px; margin: 16px 0 0; padding: 6px; }
        .subtitle { color: #4b5563; margin: 4px 0 16px; text-align: center; }
        table { border-collapse: collapse; margin: 0; width: 100%; }
        th, td { border: 1px solid #9ca3af; padding: 6px; text-align: left; vertical-align: top; }
        th { background: #f3f4f6; font-weight: bold; }
        .label { background: #f9fafb; font-weight: bold; width: 22%; }
        .number { text-align: right; }
        .result { background: #ecfeff; font-size: 16px; font-weight: bold; text-align: center; }
        .footer { color: #6b7280; font-size: 10px; margin-top: 20px; text-align: right; }
    </style>
    @include('pdf.partials.typography')
</head>
<body>
    @include('pdf.partials.system_footer')
    @include('pdf.partials.company_logo', ['company' => $product->company])
    @php
        $measurements = $product->cardboard_measurements ?? [];
        $lengthUnit = $product->company?->length_unit?->value ?? 'm';
        $weightUnit = $product->company?->weight_unit?->value ?? 'kg';
        $value = fn ($field, $suffix = '') => filled(data_get($product, $field)) ? data_get($product, $field).$suffix : 'Não informado';
        $money = fn ($field) => filled($product->{$field}) ? 'R$ '.number_format((float) $product->{$field}, 2, ',', '.') : 'Não informado';
        $cardboardProductType = $product->cardboard_product_type instanceof \App\Enums\CardboardProductType
            ? $product->cardboard_product_type->value
            : ($product->cardboard_product_type ?: 'box');
        if ($cardboardProductType === 'briefcase') {
            $measurements = $product->briefcase_measurements ?? [];
        }
        $measurement = fn ($field) => filled($measurements[$field] ?? null) ? str_replace('.', ',', $measurements[$field]).' '.$lengthUnit : 'Não informado';
        $operationalProfile = $product->company?->operational_profile;
        $isCardboardProfile = $operationalProfile instanceof \App\Enums\OperationalProfile
            ? $operationalProfile === \App\Enums\OperationalProfile::CardboardPackaging
            : $operationalProfile === \App\Enums\OperationalProfile::CardboardPackaging->value;
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

    @if($isCardboardProfile && in_array($cardboardProductType, ['box', 'briefcase'], true))
        <h2>{{ $cardboardProductType === 'briefcase' ? 'Medidas da maleta' : 'Medidas internas da embalagem' }}</h2>
        <table>
            <tr><th>{{ $cardboardProductType === 'briefcase' ? 'Comprimento' : 'Comprimento interno' }}</th><th>{{ $cardboardProductType === 'briefcase' ? 'Largura' : 'Largura interna' }}</th><th>{{ $cardboardProductType === 'briefcase' ? 'Altura' : 'Altura interna' }}</th>@if($cardboardProductType === 'briefcase')<th>Altura auxiliar</th>@endif</tr>
            <tr><td>{{ $measurement('internal_length') }}</td><td>{{ $measurement('internal_width') }}</td><td>{{ $measurement('internal_height') }}</td>@if($cardboardProductType === 'briefcase')<td>{{ $measurement('auxiliary_height') }}</td>@endif</tr>
        </table>

        <h2>Composição do comprimento da chapa</h2>
        <table>
            <tr><th>{{ $cardboardProductType === 'briefcase' ? 'Aba' : 'Aba esquerda' }}</th><th>{{ $cardboardProductType === 'briefcase' ? 'Larg.' : 'Altura esquerda' }}</th><th>Comprimento</th><th>{{ $cardboardProductType === 'briefcase' ? 'Larg.' : 'Altura direita' }}</th><th>{{ $cardboardProductType === 'briefcase' ? 'Comp.' : 'Aba direita' }}</th><th>Total</th></tr>
            <tr>
                <td>{{ $measurement('left_flap') }}</td><td>{{ $measurement($cardboardProductType === 'briefcase' ? 'left_width' : 'left_height') }}</td><td>{{ $measurement('sheet_length') }}</td>
                <td>{{ $measurement($cardboardProductType === 'briefcase' ? 'right_width' : 'right_height') }}</td><td>{{ $measurement($cardboardProductType === 'briefcase' ? 'second_length' : 'right_flap') }}</td><td>{{ \App\Support\CardboardMeasurements::format($lengthTotal) }} {{ $lengthUnit }}</td>
            </tr>
        </table>

        <h2>Composição da largura da chapa</h2>
        <table>
            @if($cardboardProductType === 'briefcase')
                <tr><th>Aba</th><th>Altura</th><th>Altura aux.</th><th>Aba</th><th>Total</th></tr>
            @else
                <tr><th>Aba superior</th><th>Altura superior</th><th>Largura</th><th>Altura inferior</th><th>Aba inferior</th><th>Total</th></tr>
            @endif
            <tr>
                @if($cardboardProductType === 'briefcase')
                    <td>{{ $measurement('top_flap') }}</td><td>{{ $measurement('height') }}</td><td>{{ $measurement('width_auxiliary_height') }}</td><td>{{ $measurement('bottom_flap') }}</td><td>{{ \App\Support\CardboardMeasurements::format($widthTotal) }} {{ $lengthUnit }}</td>
                @else
                    <td>{{ $measurement('top_flap') }}</td><td>{{ $measurement('top_height') }}</td><td>{{ $measurement('sheet_width') }}</td>
                    <td>{{ $measurement('bottom_height') }}</td><td>{{ $measurement('bottom_flap') }}</td><td>{{ \App\Support\CardboardMeasurements::format($widthTotal) }} {{ $lengthUnit }}</td>
                @endif
            </tr>
        </table>
        <table><tr><td class="result">Tamanho da chapa: {{ \App\Support\CardboardMeasurements::format($lengthTotal) }} × {{ \App\Support\CardboardMeasurements::format($widthTotal) }} {{ $lengthUnit }}</td></tr></table>
    @elseif($isCardboardProfile && $cardboardProductType === 'sheet')
        <h2>Medidas da chapa</h2>
        <table>
            <tr><th>Comprimento</th><th>Largura</th></tr>
            <tr><td>{{ $measurement('simple_sheet_length') }}</td><td>{{ $measurement('simple_sheet_width') }}</td></tr>
        </table>
        <table><tr><td class="result">Tamanho da chapa: {{ \App\Support\CardboardMeasurements::format((float) ($measurements['simple_sheet_length'] ?? 0)) }} × {{ \App\Support\CardboardMeasurements::format((float) ($measurements['simple_sheet_width'] ?? 0)) }} {{ $lengthUnit }}</td></tr></table>
    @elseif($isCardboardProfile && $cardboardProductType === 'corner')
        @php
            $lengthTotal = \App\Support\CardboardMeasurements::cornerLengthTotal($measurements);
            $widthTotal = \App\Support\CardboardMeasurements::cornerWidthTotal($measurements);
        @endphp
        <h2>Medidas da cantoneira</h2>
        <table>
            <tr><th>Comprimento</th><th>Comprimento total</th></tr>
            <tr><td>{{ $measurement('corner_length') }}</td><td>{{ \App\Support\CardboardMeasurements::format($lengthTotal) }} {{ $lengthUnit }}</td></tr>
        </table>
        <h2>Composição da largura da chapa</h2>
        <table>
            <tr><th>Altura 1</th><th>Largura</th><th>Altura 2</th><th>Largura total</th></tr>
            <tr><td>{{ $measurement('corner_height_1') }}</td><td>{{ $measurement('corner_width') }}</td><td>{{ $measurement('corner_height_2') }}</td><td>{{ \App\Support\CardboardMeasurements::format($widthTotal) }} {{ $lengthUnit }}</td></tr>
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
