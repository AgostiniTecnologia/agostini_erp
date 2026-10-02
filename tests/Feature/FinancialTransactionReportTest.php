<?php

namespace Tests\Feature;

use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\FinancialTransaction;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Mockery;
use Tests\TestCase;

class FinancialTransactionReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_separates_income_and_expenses_and_calculates_totals(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->for($company)->create();
        $this->actingAs($user);

        $incomeAccount = ChartOfAccount::create([
            'company_id' => $company->uuid,
            'code' => '1',
            'name' => 'Receitas',
            'type' => ChartOfAccount::TYPE_REVENUE,
        ]);
        $expenseAccount = ChartOfAccount::create([
            'company_id' => $company->uuid,
            'code' => '2',
            'name' => 'Despesas',
            'type' => ChartOfAccount::TYPE_EXPENSE,
        ]);

        $this->transaction($company, $user, $incomeAccount, FinancialTransaction::TYPE_INCOME, 10000, '2026-09-10');
        $this->transaction($company, $user, $expenseAccount, FinancialTransaction::TYPE_EXPENSE, 2500, '2026-09-20');
        $this->transaction($company, $user, $incomeAccount, FinancialTransaction::TYPE_INCOME, 99999, '2026-08-31');

        $pdf = Mockery::mock(\Barryvdh\DomPDF\PDF::class);
        Pdf::shouldReceive('loadView')
            ->once()
            ->with('pdf.financial_transactions_report', Mockery::on(function (array $data) use ($company): bool {
                return $data['company']->is($company)
                    && $data['incomeTransactions']->count() === 1
                    && $data['expenseTransactions']->count() === 1
                    && $data['incomeTotal'] === 100.0
                    && $data['expenseTotal'] === 25.0
                    && $data['generalTotal'] === 75.0;
            }))
            ->andReturn($pdf);
        $pdf->shouldReceive('stream')
            ->once()
            ->with(Mockery::pattern('/^relatorio_lancamentos_financeiros_/'))
            ->andReturn(response('pdf'));

        $this->get(route('financial-transactions.report.pdf', [
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-30',
        ]))->assertOk();
    }

    public function test_report_requires_a_valid_period_and_authentication(): void
    {
        $route = Route::getRoutes()->getByName('financial-transactions.report.pdf');
        $this->assertContains('auth', $route->gatherMiddleware());

        $company = Company::factory()->create();
        $user = User::factory()->for($company)->create();

        $this->actingAs($user);
        $this->get(route('financial-transactions.report.pdf', [
            'start_date' => '2026-10-02',
            'end_date' => '2026-10-01',
        ]))
            ->assertSessionHasErrors('end_date');
    }

    private function transaction(
        Company $company,
        User $user,
        ChartOfAccount $account,
        string $type,
        int $amount,
        string $date,
    ): FinancialTransaction {
        return FinancialTransaction::create([
            'company_id' => $company->uuid,
            'chart_of_account_uuid' => $account->uuid,
            'description' => 'Lançamento de teste',
            'amount' => $amount,
            'type' => $type,
            'transaction_date' => $date,
            'user_id' => $user->uuid,
        ]);
    }
}
