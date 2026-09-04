<?php

namespace App\Models;

use App\Models\Scopes\TenantScope;
use App\Services\OperationalProfileResolver;
use App\Support\CompanyMeasurementSettings;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
// Precisa importar BelongsToMany aqui!
use Illuminate\Database\Eloquent\Relations\BelongsTo; // <-- Importar BelongsTo
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
// Importar BelongsToMany
use Illuminate\Support\Facades\Auth;

class Product extends Model
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
        'company_id', // <-- Adicionar company_id aqui
        'name',
        'sku',
        'description',
        'unit_of_measure',
        'standard_cost',
        'sale_price',
        'minimum_sale_price',
        'stock',
        'weight_net',
        'weight',
        'length',
        'width',
        'height',
        'cardboard_measurements',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'standard_cost' => 'decimal:2', // Manter casts existentes
        'sale_price' => 'decimal:2',
        'minimum_sale_price' => 'decimal:2',
        'cardboard_measurements' => 'array',
    ];

    // --- RELAÇÕES ---

    /**
     * Get the company that owns the product.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id', 'uuid');
    }

    /**
     * Get the production order items associated with the product.
     */
    public function productionOrderItems(): HasMany
    {
        return $this->hasMany(ProductionOrderItem::class, 'product_uuid', 'uuid');
    }

    /**
     * Define o relacionamento Muitos-para-Muitos com ProductionStep.
     * ESTE MÉTODO PRECISA ESTAR AQUI!
     */
    public function productionSteps(): BelongsToMany // <-- Método necessário
    {
        return $this->belongsToMany(
            ProductionStep::class,      // Modelo relacionado
            'product_production_step',  // Nome da tabela pivot (igual à migration)
            'product_uuid',             // Chave estrangeira deste modelo na pivot
            'production_step_uuid'      // Chave estrangeira do modelo relacionado na pivot
        )
            ->withPivot('step_order') // <-- Informa sobre a coluna extra 'step_order'
            ->orderBy('step_order');  // <-- Opcional: Ordena as etapas
    }

    // --- FIM RELAÇÕES ---

    /**
     * Define o relacionamento Muitos-para-Muitos com RawMaterial.
     */
    public function rawMaterials(): BelongsToMany
    {
        return $this->belongsToMany(
            RawMaterial::class,
            'product_raw_material',
            'product_id',
            'raw_material_id'
        )->withPivot(['quantity', 'unit_of_measure'])
            ->withTimestamps();
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function (Model $model) {
            if (empty($model->company_id)) {
                if (Auth::check() && Auth::user()->company_id) {
                    $model->company_id = Auth::user()->company_id;
                }
            }

            if (Auth::check() && ! app(OperationalProfileResolver::class)->isCardboardPackaging()) {
                unset($model->cardboard_measurements);
            }

            if (self::hasInternalMeasurements($model->cardboard_measurements)
                && app(OperationalProfileResolver::class)->isCardboardPackaging()) {
                $company = CompanyMeasurementSettings::company();
                $model->cardboard_measurements = \App\Support\CardboardMeasurements::fromInternalDimensions(
                    $model->cardboard_measurements,
                    $company?->fold_margin ?? 5,
                    $company?->length_flap_default ?? 60,
                );
            }
        });

        static::updating(function (Product $product) {
            if (Auth::check()
                && $product->isDirty('cardboard_measurements')
                && ! app(OperationalProfileResolver::class)->isCardboardPackaging()) {
                $product->cardboard_measurements = $product->getOriginal('cardboard_measurements');
            }

            if ($product->isDirty('cardboard_measurements')
                && self::hasInternalMeasurements($product->cardboard_measurements)
                && app(OperationalProfileResolver::class)->isCardboardPackaging()) {
                $company = CompanyMeasurementSettings::company();
                $product->cardboard_measurements = \App\Support\CardboardMeasurements::fromInternalDimensions(
                    $product->cardboard_measurements ?? [],
                    $company?->fold_margin ?? 5,
                    $company?->length_flap_default ?? 60,
                );
            }
        });
    }

    private static function hasInternalMeasurements(?array $measurements): bool
    {
        return collect(['internal_length', 'internal_width', 'internal_height'])
            ->contains(fn (string $field): bool => filled($measurements[$field] ?? null));
    }
}
