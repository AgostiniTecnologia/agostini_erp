<?php

namespace App\Services;

use App\Models\Company;

class ProductionReportService
{
    public function __construct(
        private readonly AiReportDataService $dataService,
        private readonly AiReportService $aiService,
        private readonly SmartReportAnalysisService $analysisService,
    ) {}

    public function generate(string $companyId): array
    {
        $dataset = $this->dataService->buildDataset($companyId);
        $products = collect($dataset['products'] ?? [])
            ->map(fn (array $product): array => [
                'name' => $product['product'] ?? '—',
                'avg' => (int) ($product['avg_effective_seconds'] ?? 0),
            ])
            ->sortByDesc('avg')
            ->values();
        $top = $products->take(10);
        $labels = $top->pluck('name')->all() ?: ['Sem dados'];
        $values = $top->pluck('avg')->all() ?: [0];
        $historyLead = collect($dataset['production_history'] ?? [])
            ->pluck('lead_time_seconds')
            ->map(fn ($value): int => (int) $value)
            ->reverse()
            ->values()
            ->all() ?: [0, 0];

        $calculatedAnalysis = $this->analysisService->production($dataset);
        $context = json_encode([
            'dashboard' => $dataset['dashboard'] ?? [],
            'top_products' => $top->all(),
            'pause_reasons' => array_slice($dataset['pause_reasons'] ?? [], 0, 10),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        return [
            'generated_at' => now(),
            'company' => Company::findOrFail($companyId),
            'dashboard' => $dataset['dashboard'] ?? [],
            'rows' => $dataset['products'] ?? [],
            'pause_reasons' => $dataset['pause_reasons'] ?? [],
            'history' => $dataset['production_history'] ?? [],
            'analysis' => $this->aiService->enhance($calculatedAnalysis, $context, 'melhoria contínua e produção'),
            'images' => [
                'bar' => $this->aiService->makeBarChartBase64($labels, $values),
                'line' => $this->aiService->makeLineChartWithRegressionBase64($historyLead),
            ],
        ];
    }
}
