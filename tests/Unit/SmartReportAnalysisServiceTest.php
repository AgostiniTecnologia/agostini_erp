<?php

namespace Tests\Unit;

use App\Models\TimeClockEntry;
use App\Models\User;
use App\Services\AiReportService;
use App\Services\SmartReportAnalysisService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SmartReportAnalysisServiceTest extends TestCase
{
    public function test_production_analysis_is_useful_without_an_agent(): void
    {
        $analysis = app(SmartReportAnalysisService::class)->production([
            'dashboard' => ['total_orders' => 8, 'avg_lead_time_seconds' => 7200],
            'products' => [
                ['product' => 'Caixa A', 'avg_effective_seconds' => 3600, 'avg_dead_seconds' => 300, 'count' => 3],
                ['product' => 'Caixa B', 'avg_effective_seconds' => 5400, 'avg_dead_seconds' => 900, 'count' => 2],
            ],
            'pause_reasons' => [
                ['motivo' => 'Ajuste de máquina', 'tipo' => 'Tempo morto', 'total_seconds' => 1800],
            ],
            'production_history' => [
                ['lead_time_seconds' => 3600],
                ['lead_time_seconds' => 4200],
                ['lead_time_seconds' => 5400],
                ['lead_time_seconds' => 6000],
            ],
        ]);

        $this->assertStringContainsString('Maior tempo efetivo médio: Caixa B', $analysis);
        $this->assertStringContainsString('Principal motivo de pausa: Ajuste de máquina', $analysis);
        $this->assertStringContainsString('Ações recomendadas:', $analysis);
    }

    public function test_time_clock_analysis_detects_incomplete_pairs_and_manual_entries(): void
    {
        $user = new User(['name' => 'Ana']);
        $user->setAttribute('uuid', 'employee-1');

        $entries = collect([
            $this->entry($user, TimeClockEntry::TYPE_CLOCK_IN, '2026-09-01 08:00:00'),
            $this->entry($user, TimeClockEntry::TYPE_MANUAL_ENTRY, '2026-09-01 17:00:00', TimeClockEntry::STATUS_ALERT),
        ]);

        $analysis = app(SmartReportAnalysisService::class)->timeClock($entries);

        $this->assertStringContainsString('1 entrada(s) manual(is)', $analysis);
        $this->assertStringContainsString('1 dia(s) com entrada ou saída sem o respectivo par', $analysis);
        $this->assertStringContainsString('pontualidade só pode ser concluída', $analysis);
    }

    public function test_ai_enhancement_returns_calculated_analysis_when_api_is_not_configured(): void
    {
        config()->set('openai.api_key', '');
        $service = app(AiReportService::class);

        $this->assertSame(
            'Análise calculada',
            $service->enhance('Análise calculada', '{}', 'produção'),
        );
    }

    public function test_ai_enhancement_returns_calculated_analysis_when_api_fails(): void
    {
        config()->set('openai.api_key', 'test-key');
        Http::fake(['api.openai.com/*' => Http::response(['error' => ['message' => 'offline']], 503)]);

        $analysis = app(AiReportService::class)->enhance('Análise calculada', '{}', 'produção');

        $this->assertSame('Análise calculada', $analysis);
        Http::assertSentCount(1);
    }

    private function entry(User $user, string $type, string $recordedAt, string $status = TimeClockEntry::STATUS_NORMAL): TimeClockEntry
    {
        $entry = new TimeClockEntry([
            'user_id' => $user->getKey(),
            'type' => $type,
            'status' => $status,
            'recorded_at' => Carbon::parse($recordedAt),
        ]);
        $entry->setRelation('user', $user);

        return $entry;
    }
}
