<?php

namespace App\Services;

use App\Enums\OperationalProfile;
use App\Models\Company;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;

class OperationalProfileResolver
{
    public function current(): OperationalProfile
    {
        return $this->forUser(Auth::user());
    }

    public function forUser(?Authenticatable $user): OperationalProfile
    {
        if (! $user || ! $user->getAttribute('company_id')) {
            return OperationalProfile::Standard;
        }

        $company = $user->getRelationValue('company');

        if (! $company instanceof Company || $company->getKey() !== $user->getAttribute('company_id')) {
            return OperationalProfile::Standard;
        }

        return $this->forCompany($company);
    }

    public function forCompany(?Company $company): OperationalProfile
    {
        if (! $company) {
            return OperationalProfile::Standard;
        }

        $profile = $company->getRawOriginal('operational_profile')
            ?? $company->getAttributes()['operational_profile']
            ?? null;

        if ($profile instanceof OperationalProfile) {
            return $profile;
        }

        return OperationalProfile::tryFrom((string) $profile) ?? OperationalProfile::Standard;
    }

    public function isCardboardPackaging(): bool
    {
        return $this->current() === OperationalProfile::CardboardPackaging;
    }
}
