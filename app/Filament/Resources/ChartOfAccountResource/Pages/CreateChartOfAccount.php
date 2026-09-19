<?php

namespace App\Filament\Resources\ChartOfAccountResource\Pages;

use App\Filament\Resources\ChartOfAccountResource;
use App\Models\ChartOfAccount; // Modelo correto
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth; // Para obter o company_id
use Illuminate\Support\Facades\DB;

class CreateChartOfAccount extends CreateRecord
{
    protected static string $resource = ChartOfAccountResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $companyId = Auth::user()->company_id;
        if (! $companyId) {
            // Lançar uma exceção ou notificação se o usuário não tiver empresa
            // Isso não deveria acontecer se o TenantScope estiver funcionando corretamente
            // ou se o acesso ao resource for restrito.
            throw new \Exception('Usuário não associado a uma empresa.');
        }
        $data['company_id'] = $companyId;

        return $data;
    }

    protected function handleRecordCreation(array $data): Model
    {
        return DB::transaction(function () use ($data): Model {
            $data['code'] = ChartOfAccount::generateNextCode(
                $data['company_id'],
                $data['parent_uuid'] ?? null,
            );

            return static::getModel()::create($data);
        });
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Conta contábil criada com sucesso!';
    }
}
