@props(['account', 'level' => 0, 'monthStrings' => []])

@php
    $padding = 16 + ($level * 16);
    $isParent = $account->childAccounts->isNotEmpty();
    $rowClasses = $isParent
        ? 'bg-gray-50/80 dark:bg-white/[0.04]'
        : 'bg-white hover:bg-primary-50/40 dark:bg-gray-900 dark:hover:bg-white/[0.03]';
@endphp

<tr class="transition-colors {{ $rowClasses }}">
    <td
        class="sticky left-0 z-10 overflow-hidden border-r border-gray-200 px-2 py-2 dark:border-white/10 {{ $isParent ? 'bg-gray-50 dark:bg-gray-800' : 'bg-white dark:bg-gray-900' }}"
        style="padding-left: {{ $padding }}px"
        title="{{ $account->code }} — {{ $account->name }}"
    >
        <div class="flex min-w-0 items-center gap-1.5">
            @if($isParent)
                <span class="h-1.5 w-1.5 rounded-full bg-primary-500"></span>
            @endif
            <span class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ $account->code }}</span>
            <span class="truncate {{ $isParent ? 'font-semibold text-gray-950 dark:text-white' : 'text-gray-700 dark:text-gray-300' }}">
                {{ $account->name }}
            </span>
        </div>
    </td>

    <td class="px-1 py-1.5 text-center">
        @if($isParent)
            <input
                type="text"
                inputmode="decimal"
                class="w-full min-w-0 rounded-md border-gray-300 bg-white px-1 py-1 text-right text-[10px] tabular-nums shadow-sm transition focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                value="{{ $this->cashFlowsMap[$account->uuid]['goal'] ?? '' }}"
                wire:change="updateMetaCell('{{ $account->uuid }}', $event.target.value)"
            >
        @else
            <span class="text-gray-300 dark:text-gray-600">—</span>
        @endif
    </td>

    @foreach($monthStrings as $month)
        @php
            $value = $isParent
                ? $this->getParentSum($account, $month)
                : $this->getCellValue($account->uuid, $month);
        @endphp

        <td class="overflow-hidden px-1 py-1.5 text-right">
            @if($isParent)
                <span class="block truncate whitespace-nowrap text-[10px] font-semibold tabular-nums text-gray-700 dark:text-gray-200" title="{{ is_null($value) ? '' : number_format($value, 2, ',', '.') }}">
                    {{ is_null($value) ? '' : number_format($value, 2, ',', '.') }}
                </span>
            @else
                <input
                    type="text"
                    inputmode="decimal"
                    class="w-full min-w-0 rounded-md border-gray-300 bg-white px-1 py-1 text-right text-[10px] tabular-nums shadow-sm transition focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                    value="{{ is_null($value) ? '' : number_format($value, 2, ',', '.') }}"
                    wire:change="updateCell('{{ $account->uuid }}', '{{ $month }}', $event.target.value)"
                >
            @endif
        </td>
    @endforeach
</tr>

@foreach($account->childAccounts as $child)
    @include('filament.partials.cash-flow-row', [
        'account' => $child,
        'level' => $level + 1,
        'monthStrings' => $monthStrings,
    ])
@endforeach
