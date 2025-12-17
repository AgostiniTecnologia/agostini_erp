<?php

namespace App\Models;

use App\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

class RawMaterial extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $primaryKey = 'uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'company_id',
        'name',
        'sku',
        'description',
        'unit_of_measure',
        'standard_cost',
        'stock' // Opcional, dependendo da lógica de controle de estoque
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'standard_cost' => 'decimal:2',
        // Removidos sale_price e minimum_sale_price, pois não existem na migration
    ];

    // --- RELAÇÕES ---

    /**
     * Get the company that owns the RawMaterial.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id', 'uuid');
    }

    // --- SCOPES E HOOKS ---

    public function products()
    {
        return $this->belongsToMany(Product::class, 'product_raw_material', 'raw_material_id', 'product_id')
                    ->withPivot(['quantity', 'unit_of_measure'])
                    ->withTimestamps();
    }

    protected static function booted(): void
    {
        // Aplica filtro global de tenant
        static::addGlobalScope(new TenantScope);

        // Preenche company_id automaticamente ao criar
        static::creating(function (Model $model) {
            if (empty($model->company_id) && Auth::check() && Auth::user()->company_id) {
                $model->company_id = Auth::user()->company_id;
            }
        });
    }
}
