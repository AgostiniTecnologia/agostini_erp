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

    public const TYPE_ASSET = 'asset';
    public const TYPE_LIABILITY = 'liability';
    public const TYPE_EQUITY = 'equity';
    public const TYPE_REVENUE = 'revenue';
    public const TYPE_EXPENSE = 'expense';

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope());

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

    public function childAccounts(): HasMany
    {
        return $this->hasMany(ChartOfAccount::class, 'parent_uuid', 'uuid');
    }

    /**
     * Nova função segura: retorna todas as contas descendentes SOMENTE da empresa atual
     */
    public function getAllDescendantUuidsIncludingSelf(): array
    {
        $companyId = Auth::user()->company_id;

        $allAccounts = ChartOfAccount::query()
            ->where('company_id', $companyId)
            ->get()
            ->keyBy('uuid');

        $uuids = [$this->uuid];

        $searching = [$this->uuid];

        while (!empty($searching)) {
            $parent = array_pop($searching);

            foreach ($allAccounts as $account) {
                if ($account->parent_uuid === $parent) {
                    $uuids[] = $account->uuid;
                    $searching[] = $account->uuid;
                }
            }
        }

        return array_unique($uuids);
    }

    /**
     * Busca valores da conta + descendentes filtrando empresa corretamente
     */
    public function getValuesForPeriod(Carbon $startDate, Carbon $endDate, ?string $tipo = null): float
    {
        $companyId = Auth::user()->company_id;

        $accountUuids = $this->getAllDescendantUuidsIncludingSelf();

        $query = FinancialTransaction::query()
            ->where('company_id', $companyId)
            ->whereIn('chart_of_account_uuid', $accountUuids)
            ->whereBetween('transaction_date', [
                $startDate->toDateString(),
                $endDate->toDateString()
            ]);

        if ($tipo === 'entrada') {
            $query->where('type', FinancialTransaction::TYPE_INCOME);
            $total = $query->sum('amount');
        } elseif ($tipo === 'saida') {
            $query->where('type', FinancialTransaction::TYPE_EXPENSE);
            $total = $query->sum('amount');
        } else {
            // saldo líquido
            $total = $query->sum(DB::raw("
                CASE 
                    WHEN type = '" . FinancialTransaction::TYPE_INCOME . "' THEN amount
                    ELSE -amount
                END
            "));
        }

        return (float) ($total / 100);
    }
}
