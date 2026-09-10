<?php

namespace App\Services;

use App\Models\TimeClockEntry;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class TimeClockReportService
{
    public function __construct(
        private readonly SmartReportAnalysisService $analysisService,
        private readonly AiReportService $aiService,
    ) {}

    public function gerarRelatorio(string $inicio, string $fim): array
    {
        $user = Auth::user();
        $companyId = $user?->company_id;

        if (! $user || ! $companyId) {
            throw new \RuntimeException('Usuário não possui empresa vinculada.');
        }

        // Todas as batidas dos usuários da empresa
        $entries = TimeClockEntry::with(['user', 'company', 'approver'])
            ->where('company_id', $companyId)
            ->whereBetween('recorded_at', [
                Carbon::parse($inicio)->startOfDay(),
                Carbon::parse($fim)->endOfDay(),
            ])
            ->orderBy('recorded_at')
            ->get();

        $calculatedAnalysis = $this->analysisService->timeClock($entries);
        $context = json_encode([
            'period' => ['start' => $inicio, 'end' => $fim],
            'totals_by_type' => $entries->countBy('type')->all(),
            'totals_by_status' => $entries->countBy('status')->all(),
            'employees' => $entries->groupBy('user_id')->map(fn ($items) => [
                'name' => $items->first()->user?->name ?? 'Não identificado',
                'entries' => $items->count(),
                'manual_entries' => $items->where('type', TimeClockEntry::TYPE_MANUAL_ENTRY)->count(),
                'alerts' => $items->where('status', TimeClockEntry::STATUS_ALERT)->count(),
            ])->values()->all(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        $analysis = $this->aiService->enhance($calculatedAnalysis, $context, 'recursos humanos e controle de jornada');

        return [
            'entries' => $entries,
            'inicio' => $inicio,
            'fim' => $fim,
            'generatedAt' => now(),
            'company' => $user->company,
            'analysis' => $analysis,
        ];
    }
}
