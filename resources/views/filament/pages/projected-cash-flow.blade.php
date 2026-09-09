@php
    $currentYear = now()->year;
    $previousYear = $currentYear - 1;
@endphp

<x-filament-panels::page>
    <div class="space-y-4">
        <div class="flex flex-col gap-4 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-sm font-semibold text-gray-950 dark:text-white">Planejamento de {{ $currentYear }}</p>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    Informe metas e projeções mensais diretamente nas células da tabela.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <x-filament::button
                    size="sm"
                    color="gray"
                    icon="heroicon-o-document-arrow-down"
                    wire:click="downloadPreviousYearReport"
                    wire:loading.attr="disabled"
                    wire:target="downloadPreviousYearReport"
                >
                    Relatório {{ $previousYear }}
                </x-filament::button>

                <x-filament::button
                    size="sm"
                    color="primary"
                    icon="heroicon-o-document-arrow-down"
                    wire:click="downloadReport"
                    wire:loading.attr="disabled"
                    wire:target="downloadReport"
                >
                    Relatório {{ $currentYear }}
                </x-filament::button>
            </div>
        </div>

        <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="flex flex-col gap-3 border-b border-gray-200 px-4 py-3 dark:border-white/10 sm:flex-row sm:items-center sm:justify-between">
                <label class="inline-flex cursor-pointer items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
                    <input
                        type="checkbox"
                        wire:model="replicateAllMonths"
                        class="rounded border-gray-300 text-primary-600 shadow-sm focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800"
                    >
                    <span>Replicar valor para todos os meses</span>
                </label>

                <x-filament::button
                    size="xs"
                    color="danger"
                    icon="heroicon-o-trash"
                    wire:click="clearTable"
                    wire:loading.attr="disabled"
                    wire:target="clearTable"
                    wire:confirm="Deseja realmente limpar todas as metas, projeções e investimentos exibidos nesta tabela?"
                >
                    Limpar tabela
                </x-filament::button>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full table-fixed border-separate border-spacing-0 text-xs text-gray-700 dark:text-gray-300">
                    <thead class="text-xs uppercase tracking-wide text-gray-600 dark:text-gray-300">
                        <tr>
                            <th class="sticky left-0 top-0 z-30 w-1/4 border-b border-r border-gray-200 bg-gray-100 px-3 py-2.5 text-left font-semibold dark:border-white/10 dark:bg-gray-800">
                                Plano de contas
                            </th>
                            <th class="sticky top-0 z-20 border-b border-gray-200 bg-gray-100 px-1 py-2.5 text-center text-[10px] font-semibold dark:border-white/10 dark:bg-gray-800">
                                Meta
                            </th>
                            @foreach($monthHeaders as $monthDate)
                                <th class="sticky top-0 z-20 border-b border-gray-200 bg-gray-100 px-1 py-2.5 text-center text-[10px] font-semibold dark:border-white/10 dark:bg-gray-800">
                                    {{ \Carbon\Carbon::parse($monthDate)->translatedFormat('M/Y') }}
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                        @forelse($accounts as $account)
                            @include('filament.partials.cash-flow-row', [
                                'account' => $account,
                                'level' => 0,
                                'monthStrings' => $monthStrings,
                            ])
                        @empty
                            <tr>
                                <td colspan="{{ count($monthHeaders) + 2 }}" class="px-4 py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                                    Nenhuma conta cadastrada para montar o fluxo projetado.
                                </td>
                            </tr>
                        @endforelse

                        @php $summaryStarted = false; @endphp
                        @foreach($this->collectAllAccountsRecursively($accounts) as $account)
                            @php
                                $hasGoal = isset($cashFlowsMap[$account->uuid]['goal']) && $cashFlowsMap[$account->uuid]['goal'] > 0;
                            @endphp

                            @if($hasGoal)
                                @if(! $summaryStarted)
                                    <tr>
                                        <td colspan="{{ count($monthHeaders) + 2 }}" class="border-y border-gray-200 bg-gray-50 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:border-white/10 dark:bg-white/5 dark:text-gray-400">
                                            Acompanhamento das metas
                                        </td>
                                    </tr>
                                    @php $summaryStarted = true; @endphp
                                @endif

                                <tr class="bg-gray-50/70 dark:bg-white/[0.03]">
                                    <td colspan="2" class="border-r border-gray-200 bg-gray-50 px-2 py-2 font-medium text-gray-700 dark:border-white/10 dark:bg-gray-800 dark:text-gray-200">
                                        Receita em falta: {{ $account->code }} — {{ $account->name }}
                                    </td>
                                    @foreach($monthStrings as $month)
                                        @php
                                            $shortfall = $this->calculateShortfall($account->uuid, $month);
                                            $colorClass = match (true) {
                                                is_null($shortfall) => 'text-gray-400',
                                                $shortfall < 0 => 'text-red-700 bg-red-50 dark:text-red-300 dark:bg-red-950/30',
                                                $shortfall > 0 => 'text-green-700 bg-green-50 dark:text-green-300 dark:bg-green-950/30',
                                                default => 'text-gray-700 bg-gray-100 dark:text-gray-300 dark:bg-white/5',
                                            };
                                        @endphp
                                        <td class="overflow-hidden px-1 py-2 text-right text-[10px] font-semibold tabular-nums {{ $colorClass }}">
                                            {{ number_format(abs($shortfall), 2, ',', '.') }}
                                        </td>
                                    @endforeach
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="border-t border-gray-200 px-4 py-2 text-xs text-gray-500 dark:border-white/10 dark:text-gray-400">
                Os valores são salvos ao sair de cada campo.
            </div>
        </div>
    </div>
</x-filament-panels::page>
