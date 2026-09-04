<?php

namespace App\Models;

use App\Enums\LengthUnit;
use App\Enums\OperationalProfile;
use App\Enums\WeightUnit;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Company extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $primaryKey = 'uuid';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * Atributos que podem ser atribuídos em massa.
     */
    protected $fillable = [
        'uuid',
        'name',
        'socialName',
        'taxNumber',
        'address_zip_code',
        'address_street',
        'address_number',
        'address_complement',
        'address_district',
        'address_city',
        'address_state',
        'latitude',
        'longitude',
        'telephone',
        'operational_profile',
        'length_unit',
        'weight_unit',
        'fold_margin',
        'length_flap_default',
        'logo_path',
    ];

    /**
     * Conversões de tipo para atributos.
     */
    protected $casts = [
        'operational_profile' => OperationalProfile::class,
        'length_unit' => LengthUnit::class,
        'weight_unit' => WeightUnit::class,
        'fold_margin' => 'decimal:3',
        'length_flap_default' => 'decimal:3',
    ];

    /**
     * Relacionamento: Uma Empresa tem muitos Usuários.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'company_id', 'uuid');
    }

    public function workShifts(): HasMany
    {
        return $this->hasMany(WorkShift::class, 'company_id', 'uuid');
    }

    public function holidays(): HasMany
    {
        return $this->hasMany(Holiday::class, 'company_id', 'uuid');
    }

    /**
     * Retorna uma imagem embutida, compatível com DomPDF, sem expor arquivos
     * de outra empresa. Na ausência de logo personalizada usa a marca padrão.
     */
    public function reportLogoDataUri(): string
    {
        $disk = Storage::disk('public');

        $companyDirectory = 'company-logos/'.$this->getKey().'/';

        if ($this->logo_path
            && str_starts_with($this->logo_path, $companyDirectory)
            && $disk->exists($this->logo_path)) {
            $mimeType = $disk->mimeType($this->logo_path);

            if (in_array($mimeType, ['image/png', 'image/jpeg', 'image/webp'], true)) {
                return 'data:'.$mimeType.';base64,'.base64_encode($disk->get($this->logo_path));
            }
        }

        return static::defaultReportLogoDataUri();
    }

    public static function defaultReportLogoDataUri(): string
    {
        $path = public_path('images/logo-agostini-full_color-1-horizontal.png');

        return 'data:image/png;base64,'.base64_encode(file_get_contents($path));
    }

    // Em App\Models\Company.php e App\Models\Client.php
    public function getFullAddress(): string
    {
        return trim(implode(', ', array_filter([
            $this->address_street,
            $this->address_number,
            $this->address_complement,
            $this->address_neighborhood,
            $this->address_city,
            $this->address_state,
            $this->address_zip_code,
        ])));
    }
}
