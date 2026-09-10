<?php

namespace App\Services;

use App\Models\TimeClockEntry;
use Illuminate\Support\Collection;

class SmartReportAnalysisService
{
    public function production(array $dataset): string
    {
        $dashboard = $dataset['dashboard'] ?? [];
        $products = collect($dataset['products'] ?? [])
            ->filter(fn (array $product): bool => ($product['count'] ?? 0) > 0);
        $pauses = collect($dataset['pause_reasons'] ?? []);
        $history = collect($dataset['production_history'] ?? [])->reverse()->values();

        $lines = [
            'Análise calculada a partir dos dados do sistema:',
            sprintf(
                '• Foram encontradas %d ordens, com lead time médio de %s.',
                (int) ($dashboard['total_orders'] ?? 0),
                $this->duration((int) ($dashboard['avg_lead_time_seconds'] ?? 0)),
            ),
        ];

        if ($products->isEmpty()) {
            $lines[] = '• Não há ordens concluídas suficientes para comparar o desempenho dos produtos.';
        } else {
            $slowest = $products->sortByDesc('avg_effective_seconds')->first();
            $highestDeadTime = $products->sortByDesc('avg_dead_seconds')->first();
            $lines[] = sprintf(
                '• Maior tempo efetivo médio: %s (%s, %d ordem(ns) concluída(s)).',
                $slowest['product'],
                $this->duration((int) $slowest['avg_effective_seconds']),
                (int) $slowest['count'],
            );

            if (($highestDeadTime['avg_dead_seconds'] ?? 0) > 0) {
                $lines[] = sprintf(
                    '• Maior tempo morto médio: %s (%s).',
                    $highestDeadTime['product'],
                    $this->duration((int) $highestDeadTime['avg_dead_seconds']),
                );
            }
        }

        $topPause = $pauses->sortByDesc('total_seconds')->first();
        $lines[] = $topPause
            ? sprintf('• Principal motivo de pausa: %s (%s no período).', $topPause['motivo'], $this->duration((int) $topPause['total_seconds']))
            : '• Não há pausas registradas no período analisado.';

        $leadTimes = $history->pluck('lead_time_seconds')->map(fn ($value): int => (int) $value);
        if ($leadTimes->count() >= 2) {
            $middle = (int) ceil($leadTimes->count() / 2);
            $olderAverage = $leadTimes->take($middle)->avg();
            $recentAverage = $leadTimes->slice($middle)->avg();
            $variation = $olderAverage > 0 ? (($recentAverage - $olderAverage) / $olderAverage) * 100 : 0;

            $lines[] = abs($variation) < 5
                ? '• O lead time recente está estável em relação à primeira metade do histórico.'
                : sprintf(
                    '• O lead time recente %s %.1f%% em relação à primeira metade do histórico.',
                    $variation > 0 ? 'aumentou' : 'diminuiu',
                    abs($variation),
                );
        } else {
            $lines[] = '• O histórico ainda é insuficiente para indicar tendência de lead time.';
        }

        $lines[] = 'Ações recomendadas:';
        $lines[] = $topPause
            ? "1. Investigar a causa raiz de “{$topPause['motivo']}” e definir responsável e prazo para reduzi-la."
            : '1. Padronizar o registro dos motivos de pausa para permitir a análise dos gargalos.';
        $lines[] = $products->isNotEmpty()
            ? "2. Revisar o processo de “{$slowest['product']}”, começando pelas etapas com maior espera e retrabalho."
            : '2. Registrar início e conclusão das ordens para formar uma base comparável.';
        $lines[] = '3. Acompanhar semanalmente lead time, tempo morto e volume concluído para validar a evolução.';

        return implode("\n", $lines);
    }

    public function timeClock(Collection $entries): string
    {
        $total = $entries->count();
        $employees = $entries->pluck('user_id')->filter()->unique()->count();
        $manual = $entries->where('type', TimeClockEntry::TYPE_MANUAL_ENTRY)->count();
        $alerts = $entries->where('status', TimeClockEntry::STATUS_ALERT)->count();

        $lines = [
            'Análise calculada a partir dos dados do sistema:',
            sprintf('• %d batida(s) de %d colaborador(es) no período.', $total, $employees),
        ];

        if ($total === 0) {
            $lines[] = '• Não há dados suficientes para avaliar frequência ou regularidade.';
            $lines[] = 'Recomendação: confirme o período e a adesão dos colaboradores ao registro de ponto.';

            return implode("\n", $lines);
        }

        $lines[] = sprintf('• %d entrada(s) manual(is) e %d ocorrência(s) em alerta.', $manual, $alerts);

        $incompleteDays = $entries
            ->groupBy(fn (TimeClockEntry $entry): string => $entry->user_id.'|'.$entry->recorded_at->toDateString())
            ->filter(function (Collection $day): bool {
                $types = $day->pluck('type');

                return $types->contains(TimeClockEntry::TYPE_CLOCK_IN)
                    xor $types->contains(TimeClockEntry::TYPE_CLOCK_OUT);
            })
            ->count();

        $lines[] = $incompleteDays > 0
            ? "• Há {$incompleteDays} dia(s) com entrada ou saída sem o respectivo par; esses registros exigem conferência."
            : '• Não foram encontrados dias com apenas entrada ou apenas saída no período.';

        $mostManual = $entries
            ->where('type', TimeClockEntry::TYPE_MANUAL_ENTRY)
            ->groupBy('user_id')
            ->sortByDesc(fn (Collection $employeeEntries): int => $employeeEntries->count())
            ->first();

        if ($mostManual) {
            $name = $mostManual->first()->user?->name ?? 'Colaborador não identificado';
            $lines[] = sprintf('• Maior volume de ajustes manuais: %s (%d).', $name, $mostManual->count());
        }

        $lines[] = 'Recomendações:';
        $lines[] = $incompleteDays > 0
            ? '1. Conferir os pares incompletos antes do fechamento da folha.'
            : '1. Manter a conferência dos pares de entrada e saída antes do fechamento.';
        $lines[] = $manual > 0
            ? '2. Revisar as justificativas das entradas manuais e suas aprovações.'
            : '2. Monitorar entradas manuais para detectar exceções futuras.';
        $lines[] = $alerts > 0
            ? '3. Tratar as ocorrências em alerta com o colaborador e registrar a decisão.'
            : '3. Manter o acompanhamento das ocorrências sinalizadas pelo sistema.';
        $lines[] = 'Observação: pontualidade só pode ser concluída comparando as batidas com a jornada cadastrada; este relatório não presume horários ausentes.';

        return implode("\n", $lines);
    }

    private function duration(int $seconds): string
    {
        if ($seconds <= 0) {
            return '0 min';
        }

        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        return $hours > 0 ? sprintf('%dh %02dmin', $hours, $minutes) : sprintf('%d min', max(1, $minutes));
    }
}
