<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('chart_of_accounts')) {
            return;
        }

        DB::transaction(function (): void {
            DB::table('chart_of_accounts')
                ->select(['uuid', 'company_id', 'parent_uuid', 'code'])
                ->orderBy('company_id')
                ->orderBy('code')
                ->orderBy('uuid')
                ->get()
                ->groupBy('company_id')
                ->each(fn (Collection $accounts) => $this->normalizeCompanyAccounts($accounts));
        });
    }

    public function down(): void
    {
        // Os códigos anteriores não podem ser reconstruídos com segurança.
    }

    private function normalizeCompanyAccounts(Collection $accounts): void
    {
        $accountIds = $accounts->pluck('uuid')->flip();
        $childrenByParent = $accounts
            ->filter(fn (object $account): bool => $account->parent_uuid !== null && $accountIds->has($account->parent_uuid))
            ->groupBy('parent_uuid');
        $roots = $accounts->filter(
            fn (object $account): bool => $account->parent_uuid === null || ! $accountIds->has($account->parent_uuid)
        );
        $newCodes = [];

        $assignCodes = function (Collection $siblings, string $prefix, int $depth) use (&$assignCodes, &$newCodes, $childrenByParent): void {
            $siblings = $siblings->sortBy([
                ['code', 'asc'],
                ['uuid', 'asc'],
            ])->values();

            foreach ($siblings as $index => $account) {
                $children = $childrenByParent->get($account->uuid, collect());
                $width = match ($depth) {
                    0, 1 => 1,
                    2 => 2,
                    default => 6,
                };
                $sequence = $index + 1;

                if ($sequence > (10 ** $width) - 1) {
                    throw new \RuntimeException("Não foi possível normalizar o plano de contas: excesso de contas no nível {$depth}.");
                }

                $segment = str_pad((string) $sequence, $width, '0', STR_PAD_LEFT);
                $code = $prefix === '' ? $segment : "{$prefix}.{$segment}";
                $newCodes[$account->uuid] = $code;

                if ($children->isNotEmpty()) {
                    $assignCodes($children, $code, $depth + 1);
                }
            }
        };

        $assignCodes($roots, '', 0);

        if (count($newCodes) !== $accounts->count()) {
            throw new \RuntimeException('Não foi possível normalizar o plano de contas porque existe uma hierarquia circular.');
        }

        foreach ($newCodes as $uuid => $code) {
            DB::table('chart_of_accounts')
                ->where('uuid', $uuid)
                ->update(['code' => '__normalizing__'.$uuid]);
        }

        foreach ($newCodes as $uuid => $code) {
            DB::table('chart_of_accounts')
                ->where('uuid', $uuid)
                ->update(['code' => $code]);
        }
    }
};
