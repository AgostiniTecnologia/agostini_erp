<?php

namespace Tests\Feature;

use App\Filament\Pages\ProjectedCashFlow;
use App\Models\CashFlow;
use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\TestCase;

class ProjectedCashFlowClearTest extends TestCase
{
    use RefreshDatabase;

    public function test_projected_cash_flow_renders_the_visual_toolbar_and_table(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->for($company)->create();
        $this->account($company, '1.1');
        Gate::before(fn (): bool => true);

        Livewire::actingAs($user)
            ->test(ProjectedCashFlow::class)
            ->assertOk()
            ->assertSee('Planejamento de '.now()->year)
            ->assertSee('Replicar valor para todos os meses')
            ->assertSee('Limpar tabela');
    }

    public function test_it_clears_only_the_current_table_for_the_authenticated_company(): void
    {
        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $user = User::factory()->for($company)->create();
        $this->actingAs($user);

        $account = $this->account($company, '1.1');
        $otherAccount = $this->account($otherCompany, '1.1');
        $currentProjection = $this->flow($company, $account, '2026-01', 'projection');
        $currentInvestment = $this->flow($company, $account, '2026-02', 'investment');
        $goal = $this->flow($company, $account, 'goal', 'goal');
        $previousProjection = $this->flow($company, $account, '2025-12', 'projection');
        $otherCompanyProjection = $this->flow($otherCompany, $otherAccount, '2026-01', 'projection');

        $page = app(ProjectedCashFlow::class);
        $page->monthStrings = ['2026-01', '2026-02'];
        $page->clearTable();

        $this->assertSoftDeleted('cash_flows', ['uuid' => $currentProjection->uuid]);
        $this->assertSoftDeleted('cash_flows', ['uuid' => $currentInvestment->uuid]);
        $this->assertSoftDeleted('cash_flows', ['uuid' => $goal->uuid]);
        $this->assertDatabaseHas('cash_flows', ['uuid' => $previousProjection->uuid, 'deleted_at' => null]);
        $this->assertDatabaseHas('cash_flows', ['uuid' => $otherCompanyProjection->uuid, 'deleted_at' => null]);
    }

    private function account(Company $company, string $code): ChartOfAccount
    {
        return ChartOfAccount::withoutGlobalScopes()->create([
            'company_id' => $company->uuid,
            'code' => $code,
            'name' => 'Conta de teste',
            'type' => ChartOfAccount::TYPE_REVENUE,
        ]);
    }

    private function flow(Company $company, ChartOfAccount $account, string $month, string $category): CashFlow
    {
        return CashFlow::withoutGlobalScopes()->create([
            'company_id' => $company->uuid,
            'chart_of_account_id' => $account->uuid,
            'month' => $month,
            'category' => $category,
            'amount' => 100,
        ]);
    }
}
