<?php

namespace App\Models;

use App\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth; // <-- Import the Auth facade
use Illuminate\Support\Carbon; // Importar Carbon para now()
use Illuminate\Validation\ValidationException;

class ProductionOrder extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    public const STATUS_COMPLETED = 'Concluída';
    public const STATUS_IN_PROGRESS = 'Em Andamento';
    public const STATUS_PAUSED = 'Pausada';

    private bool $automatedStatusTransition = false;
    protected $primaryKey = 'uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'company_id', // Good, it's fillable
        'order_number',
        'due_date',
        'start_date',
        'completion_date',
        'status',
        'notes',
        'user_uuid', // Foreign key for the user
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'due_date' => 'date',
        'start_date' => 'datetime',
        'completion_date' => 'datetime',
    ];

    // --- RELATIONSHIPS ---

    /**
     * Get the company that owns the production order.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id', 'uuid');
    }

    /**
     * Get the user responsible for the production order.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_uuid', 'uuid');
    }

    /**
     * Get the items associated with the production order.
     */
    public function items(): HasMany
    {
        return $this->hasMany(ProductionOrderItem::class, 'production_order_uuid', 'uuid');
    }

    public function productionOrderLogs(): HasMany
    {
        return $this->hasMany(ProductionOrderLog::class, 'production_order_uuid', 'uuid');
    }

    // --- END RELATIONSHIPS ---


    /**
     * Registra a data de início real da OP e muda o status para 'Em Andamento'.
     */
    public function startProduction(): void
    {
        if (is_null($this->start_date)) {
            $this->start_date = now();
        }
        if ($this->status !== self::STATUS_COMPLETED) {
            $this->status = self::STATUS_IN_PROGRESS;
        }
        $this->saveAutomatedStatusTransition();
    }

    /**
     * Pausa automaticamente a OP junto com a tarefa em execução.
     */
    public function pauseProduction(): void
    {
        if ($this->status === self::STATUS_COMPLETED) {
            return;
        }

        $this->status = self::STATUS_PAUSED;
        $this->saveAutomatedStatusTransition();
    }

    /**
     * Registra a data de conclusão real da OP e muda o status para 'Concluída'.
     */
    public function completeProduction(): void
    {
        if (is_null($this->completion_date)) {
            $this->completion_date = now();
        }
        $this->status = self::STATUS_COMPLETED;
        $this->saveAutomatedStatusTransition();
    }

    public static function automaticallyManagedStatuses(): array
    {
        return [self::STATUS_IN_PROGRESS, self::STATUS_PAUSED, self::STATUS_COMPLETED];
    }

    private function saveAutomatedStatusTransition(): void
    {
        $this->automatedStatusTransition = true;

        try {
            $this->save();
        } finally {
            $this->automatedStatusTransition = false;
        }
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope());

        static::creating(function (ProductionOrder $productionOrder) {
            if (empty($productionOrder->company_id)) {
                if (Auth::check() && Auth::user()->company_id) {
                    $productionOrder->company_id = Auth::user()->company_id;
                }
            }
        });

        static::updating(function (ProductionOrder $productionOrder): void {
            if ($productionOrder->isDirty('status')
                && in_array($productionOrder->status, self::automaticallyManagedStatuses(), true)
                && ! $productionOrder->automatedStatusTransition) {
                throw ValidationException::withMessages([
                    'status' => 'Este status é atualizado automaticamente pelo apontamento da produção.',
                ]);
            }
        });
    }
}
