<?php

namespace App\Models;

use App\Models\Scopes\TenantScope;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ChartOfAccount extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $primaryKey = 'uuid';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'company_id',
        'code',
        'name',
        'type',
        'parent_uuid',
    ];

    protected $casts = [
        // Se necessário, adicione casts
    ];

    public const TYPE_ASSET = 'asset';

    public const TYPE_LIABILITY = 'liability';

    public const TYPE_EQUITY = 'equity';

    public const TYPE_REVENUE = 'revenue';

    public const TYPE_EXPENSE = 'expense';

    public static function getTypeOptions(): array
    {
        return [
            self::TYPE_ASSET => 'Ativo',
            self::TYPE_LIABILITY => 'Passivo',
            self::TYPE_EQUITY => 'Patrimônio Líquido',
            self::TYPE_REVENUE => 'Receita',
            self::TYPE_EXPENSE => 'Despesa',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function (Model $model) {
            if (empty($model->company_id) && Auth::check() && Auth::user()->company_id) {
                $model->company_id = Auth::user()->company_id;
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id', 'uuid');
    }

    public function parentAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'parent_uuid', 'uuid');
    }

    public function childAccounts(): HasMany
    {
        return $this->hasMany(ChartOfAccount::class, 'parent_uuid', 'uuid');
    }

    /**
     * Generate the next code using the chart-of-accounts hierarchy:
     * 1, 1.1, 1.1.01, 1.1.01.000001.
     *
     * From the fourth level onward, each segment uses six digits.
     */
    public static function generateNextCode(string $companyId, ?string $parentUuid = null): string
    {
        $parent = null;

        if ($parentUuid) {
            $parent = static::withoutGlobalScopes()
                ->withTrashed()
                ->where('company_id', $companyId)
                ->whereKey($parentUuid)
                ->lockForUpdate()
                ->firstOrFail();
        }

        $segmentWidth = static::segmentWidthForChild($parent);
        $siblings = static::withoutGlobalScopes()
            ->withTrashed()
            ->where('company_id', $companyId)
            ->when(
                $parent,
                fn ($query) => $query->where('parent_uuid', $parent->uuid),
                fn ($query) => $query->whereNull('parent_uuid'),
            )
            ->lockForUpdate()
            ->pluck('code');

        $nextSegment = $siblings
            ->map(fn (string $code): int => (int) last(explode('.', $code)))
            ->max() + 1;

        $maximum = (10 ** $segmentWidth) - 1;
        if ($nextSegment > $maximum) {
            throw new RuntimeException('O limite de códigos para este nível do plano de contas foi atingido.');
        }

        $segment = str_pad((string) $nextSegment, $segmentWidth, '0', STR_PAD_LEFT);

        return $parent ? "{$parent->code}.{$segment}" : $segment;
    }

    public function getHierarchyDepthAttribute(): int
    {
        return substr_count($this->code, '.');
    }

    public function getIndentedCodeAttribute(): string
    {
        return str_repeat("\u{00A0}", $this->hierarchy_depth * 2).$this->code;
    }

    private static function segmentWidthForChild(?self $parent): int
    {
        if (! $parent) {
            return 1;
        }

        return match (substr_count($parent->code, '.')) {
            0 => 1,
            1 => 2,
            default => 6,
        };
    }

    public function financialTransactions(): HasMany
    {
        return $this->hasMany(FinancialTransaction::class, 'chart_of_account_uuid', 'uuid');
    }

    private function collectDescendantUuids(self $account, array &$uuids): void
    {
        foreach ($account->childAccounts as $child) {
            $uuids[] = $child->uuid;
            $this->collectDescendantUuids($child, $uuids);
        }
    }

    public function getAllDescendantUuidsIncludingSelf(): array
    {
        $uuids = [$this->uuid];
        // Ensure childAccounts are loaded before starting recursion
        $this->loadMissing('childAccounts');
        $this->collectDescendantUuids($this, $uuids);

        return array_unique($uuids);
    }

    /**
     * Calculates the sum of financial transactions for this account and its descendants
     * within a given period.
     * Income is positive, Expense is negative.
     */
    public function getValuesForPeriod(Carbon $startDate, Carbon $endDate, ?string $tipo = null): float
    {
        $accountUuids = $this->getAllDescendantUuidsIncludingSelf();
        $query = FinancialTransaction::query()
            ->whereIn('chart_of_account_uuid', $accountUuids)
            ->whereBetween('transaction_date', [$startDate->toDateString(), $endDate->toDateString()]);

        if ($tipo === 'entrada') {
            $query->where('type', FinancialTransaction::TYPE_INCOME);
            $total = $query->sum('amount');
        } elseif ($tipo === 'saida') {
            $query->where('type', FinancialTransaction::TYPE_EXPENSE);
            $total = $query->sum('amount');
        } else {
            // saldo líquido: entradas positivas, saídas negativas
            $total = $query->sum(DB::raw("
            CASE 
                WHEN type = '".FinancialTransaction::TYPE_INCOME."' THEN amount
                ELSE -amount 
            END
        "));
        }

        // como os valores estão em centavos, normalizamos para reais
        return (float) ($total / 100);
    }
}
