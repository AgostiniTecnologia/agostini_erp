<?php

namespace Database\Seeders;

use App\Models\ChartOfAccount;
use App\Models\Company;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ChartOfAccountSeeder extends Seeder
{
    /**
     * Define a estrutura base do plano de contas.
     * Os códigos seguem o padrão hierárquico usado pela aplicação.
     */
    private array $baseChartStructure = [
        ['name' => 'ATIVO', 'type' => ChartOfAccount::TYPE_ASSET, 'children' => [
            ['name' => 'ATIVO CIRCULANTE', 'type' => ChartOfAccount::TYPE_ASSET, 'children' => [
                ['name' => 'Caixa e Equivalentes de Caixa', 'type' => ChartOfAccount::TYPE_ASSET],
                ['name' => 'Contas a Receber', 'type' => ChartOfAccount::TYPE_ASSET],
                ['name' => 'Estoques', 'type' => ChartOfAccount::TYPE_ASSET],
            ]],
            ['name' => 'ATIVO NÃO CIRCULANTE', 'type' => ChartOfAccount::TYPE_ASSET, 'children' => [
                ['name' => 'Imobilizado', 'type' => ChartOfAccount::TYPE_ASSET],
                ['name' => 'Intangível', 'type' => ChartOfAccount::TYPE_ASSET],
            ]],
        ]],
        ['name' => 'PASSIVO', 'type' => ChartOfAccount::TYPE_LIABILITY, 'children' => [
            ['name' => 'PASSIVO CIRCULANTE', 'type' => ChartOfAccount::TYPE_LIABILITY, 'children' => [
                ['name' => 'Fornecedores', 'type' => ChartOfAccount::TYPE_LIABILITY],
                ['name' => 'Empréstimos e Financiamentos', 'type' => ChartOfAccount::TYPE_LIABILITY],
                ['name' => 'Obrigações Sociais e Trabalhistas', 'type' => ChartOfAccount::TYPE_LIABILITY],
            ]],
            ['name' => 'PASSIVO NÃO CIRCULANTE', 'type' => ChartOfAccount::TYPE_LIABILITY, 'children' => [
                ['name' => 'Empréstimos e Financiamentos (Longo Prazo)', 'type' => ChartOfAccount::TYPE_LIABILITY],
            ]],
        ]],
        ['name' => 'PATRIMÔNIO LÍQUIDO', 'type' => ChartOfAccount::TYPE_EQUITY, 'children' => [
            ['name' => 'Capital Social', 'type' => ChartOfAccount::TYPE_EQUITY],
            ['name' => 'Reservas de Lucro', 'type' => ChartOfAccount::TYPE_EQUITY],
            ['name' => 'Lucros ou Prejuízos Acumulados', 'type' => ChartOfAccount::TYPE_EQUITY],
        ]],
        ['name' => 'RECEITAS', 'type' => ChartOfAccount::TYPE_REVENUE, 'children' => [
            ['name' => 'RECEITA OPERACIONAL BRUTA', 'type' => ChartOfAccount::TYPE_REVENUE, 'children' => [
                ['name' => 'Venda de Produtos', 'type' => ChartOfAccount::TYPE_REVENUE],
                ['name' => 'Prestação de Serviços', 'type' => ChartOfAccount::TYPE_REVENUE],
            ]],
            ['name' => 'DEDUÇÕES DA RECEITA BRUTA', 'type' => ChartOfAccount::TYPE_REVENUE, 'children' => [ // Considerado "redutor" de receita
                ['name' => 'Impostos Sobre Vendas e Serviços', 'type' => ChartOfAccount::TYPE_REVENUE],
            ]],
            ['name' => 'OUTRAS RECEITAS OPERACIONAIS', 'type' => ChartOfAccount::TYPE_REVENUE],
        ]],
        ['name' => 'DESPESAS', 'type' => ChartOfAccount::TYPE_EXPENSE, 'children' => [
            ['name' => 'CUSTOS DOS PRODUTOS VENDIDOS / SERVIÇOS PRESTADOS', 'type' => ChartOfAccount::TYPE_EXPENSE, 'children' => [
                ['name' => 'Custo da Mercadoria Vendida (CMV)', 'type' => ChartOfAccount::TYPE_EXPENSE],
                ['name' => 'Custo do Serviço Prestado (CSP)', 'type' => ChartOfAccount::TYPE_EXPENSE],
            ]],
            ['name' => 'DESPESAS OPERACIONAIS', 'type' => ChartOfAccount::TYPE_EXPENSE, 'children' => [
                ['name' => 'Despesas com Vendas', 'type' => ChartOfAccount::TYPE_EXPENSE],
                ['name' => 'Despesas Administrativas', 'type' => ChartOfAccount::TYPE_EXPENSE, 'children' => [
                    ['name' => 'Aluguel', 'type' => ChartOfAccount::TYPE_EXPENSE],
                    ['name' => 'Salários e Encargos (ADM)', 'type' => ChartOfAccount::TYPE_EXPENSE],
                    ['name' => 'Energia Elétrica (ADM)', 'type' => ChartOfAccount::TYPE_EXPENSE],
                    ['name' => 'Material de Escritório', 'type' => ChartOfAccount::TYPE_EXPENSE],
                ]],
            ]],
            ['name' => 'DESPESAS FINANCEIRAS', 'type' => ChartOfAccount::TYPE_EXPENSE],
        ]],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $companies = Company::all();

        if ($companies->isEmpty()) {
            $this->command->warn('Nenhuma empresa encontrada. Crie empresas antes de popular o plano de contas.');

            // Opcionalmente, crie uma empresa padrão aqui se desejar
            // Company::factory()->create(['name' => 'Empresa Padrão Seeder']);
            // $companies = Company::all();
            // if ($companies->isEmpty()) return;
            return;
        }

        foreach ($companies as $company) {
            $this->command->info("Criando plano de contas para a empresa: {$company->name}");
            $this->createAccountsForCompany($company, $this->baseChartStructure);
        }
    }

    private function createAccountsForCompany(Company $company, array $structure, ?string $parentUuid = null): void
    {
        foreach ($structure as $accountData) {
            $existingAccount = ChartOfAccount::withoutGlobalScopes()
                ->where('company_id', $company->uuid)
                ->where('parent_uuid', $parentUuid)
                ->where('name', $accountData['name'])
                ->first();

            if ($existingAccount) {
                $createdAccountUuid = $existingAccount->uuid;
            } else {
                $account = DB::transaction(function () use ($company, $parentUuid, $accountData): ChartOfAccount {
                    return ChartOfAccount::withoutGlobalScopes()->create([
                        'uuid' => Str::uuid()->toString(),
                        'company_id' => $company->uuid,
                        'code' => ChartOfAccount::generateNextCode($company->uuid, $parentUuid),
                        'name' => $accountData['name'],
                        'type' => $accountData['type'],
                        'parent_uuid' => $parentUuid,
                    ]);
                });
                $createdAccountUuid = $account->uuid;
            }

            if (! empty($accountData['children'])) {
                $this->createAccountsForCompany($company, $accountData['children'], $createdAccountUuid);
            }
        }
    }
}
