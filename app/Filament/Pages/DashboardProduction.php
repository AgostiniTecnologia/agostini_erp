<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\AverageProductionTimes;
use App\Filament\Widgets\PauseTimesOverview;
use App\Filament\Widgets\ProductionStatsOverview;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Actions\Action;
use Illuminate\Support\Facades\Auth;

class DashboardProduction extends \Filament\Pages\Page
{
    use HasPageShield;

    protected static ?string $navigationIcon = 'heroicon-o-chart-pie';
    protected static string $view = 'filament.pages.dashboard-production';
    protected static ?string $navigationGroup = 'Produção';
    protected static ?string $navigationLabel = 'Dashboard';
    protected static ?int $navigationSort = 30;
    protected static ?string $title = 'Dashboard de Produção';

    // 1. Implementar o método getHeaderActions() para o cabeçalho estilizado
    protected function getHeaderActions(): array
    {
        // O botão "Gerar Relatório" agora é uma Action do Filament
        return [
            Action::make('Gerar Relatório')
                ->button()
                ->color('primary')
                ->icon('heroicon-o-document-text')
                // 2. Usar o 'action' do Filament para executar o JavaScript
                ->action(function () {
                    // O código JavaScript original é encapsulado aqui.
                    // Usamos a função Livewire 'js()' para injetar o script no frontend.
                    // O token de autenticação é gerado no backend e passado para o script.
                    $token = Auth::user() ? Auth::user()->createToken("api")->plainTextToken : "";
                    
                    // O script precisa ser adaptado para ser executado diretamente.
                    // Não precisamos mais do event listener, apenas da lógica de fetch.
                    $jsScript = <<<JS
                        (async () => {
                            const token = '{$token}';
                            const res = await fetch('/api/ai/production/generate-pdf', {
                                method: 'POST',
                                headers: {
                                    'Accept': 'application/pdf',
                                    'Authorization': 'Bearer ' + token,
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                                }
                            });
                            if (!res.ok) {
                                alert('Erro ao gerar PDF: ' + res.statusText);
                                return;
                            }
                            const blob = await res.blob();
                            const url = window.URL.createObjectURL(blob);
                            const a = document.createElement('a');
                            a.href = url;
                            a.download = 'production_report.pdf';
                            document.body.appendChild(a);
                            a.click();
                            a.remove();
                            window.URL.revokeObjectURL(url);
                        })();
                    JS;

                    // CORREÇÃO: A função js() deve ser chamada diretamente no objeto $this (Livewire component)
                    // e não em uma nova Action.
                    $this->js($jsScript);
                })
        ];
    }

    // O método getWidgets() permanece o mesmo
    public function getWidgets(): array
    {
        return [
            ProductionStatsOverview::class,
            PauseTimesOverview::class,
            AverageProductionTimes::class,
        ];
    }

    // ... (outras propriedades e métodos)
    protected static bool $isDiscovered = true;
    protected static ?string $slug = 'dashboard-producao';
}
