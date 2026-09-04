<?php

namespace App\Support;

use App\Models\Company;

class CompanyMeasurementSettings
{
    public static function company(): ?Company
    {
        $companyId = auth()->user()?->company_id;

        return $companyId ? Company::query()->find($companyId) : null;
    }

    public static function lengthUnit(): string
    {
        return self::company()?->length_unit?->value ?? 'm';
    }

    public static function weightUnit(): string
    {
        return self::company()?->weight_unit?->value ?? 'kg';
    }
}
