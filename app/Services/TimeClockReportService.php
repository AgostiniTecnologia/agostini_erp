<?php

namespace App\Services;

use App\Models\TimeClockEntry;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use OpenAI\Laravel\Facades\OpenAI;

class TimeClockReportService
{
    public function gerarRelatorio(string $inicio, string $fim)
    {
        $user = Auth::user();

        // Empresa do usuário autenticado
        $companyId = $user->company_id;

        // Todas as batidas dos usuários da empresa
        $entries = TimeClockEntry::with(['user', 'company', 'approver'])
            ->whereHas('user', function ($q) use ($companyId) {
                $q->where('company_id', $companyId);
            })
            ->whereBetween('recorded_at', [
                Carbon::parse($inicio)->startOfDay(),
                Carbon::parse($fim)->endOfDay(),
            ])
            ->orderBy('recorded_at')
            ->get();

        // Monta o texto que será enviado para IA
        $dadosTexto = $entries->map(function ($e) {
            return "{$e->user->name} | {$e->recorded_at} | {$e->action_type}";
        })->join("\n");

        // Chamada correta ao modelo
        $aiResponse = OpenAI::responses()->create([
            'model'  => 'gpt-4.1-mini',
            'input'  => "
                Você é um consultor profissional de RH.
                Gere um relatório detalhado sobre comportamento,
                pontualidade e ocorrências dos colaboradores.

                Empresa: {$user->company->fantasy_name}
                Período: $inicio até $fim

                Dados coletados:
                $dadosTexto

                Gere uma análise completa, profissional e com recomendações práticas.
            ",
        ]);

        // CORREÇÃO: captura correta do texto retornado
        $textoIA = $aiResponse->output[0]->content[0]->text;

        return [
            'entries'     => $entries,
            'inicio'      => $inicio,
            'fim'         => $fim,
            'generatedAt' => now(),
            'company'     => $user->company,
            'ia'          => $textoIA,
        ];
    }
}
