<?php

namespace Tests\Feature;

use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\User;
use Database\Seeders\ChartOfAccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChartOfAccountCodeTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_generates_codes_with_the_expected_width_for_each_level(): void
    {
        $company = Company::factory()->create();

        $root = $this->createAccount($company, null);
        $levelTwo = $this->createAccount($company, $root);
        $levelThree = $this->createAccount($company, $levelTwo);
        $levelFour = $this->createAccount($company, $levelThree);
        $secondLevelFour = $this->createAccount($company, $levelThree);

        $this->assertSame('1', $root->code);
        $this->assertSame('1.1', $levelTwo->code);
        $this->assertSame('1.1.01', $levelThree->code);
        $this->assertSame('1.1.01.000001', $levelFour->code);
        $this->assertSame('1.1.01.000002', $secondLevelFour->code);
    }

    public function test_it_keeps_fourth_level_accounts_in_a_six_digit_sequence(): void
    {
        $company = Company::factory()->create();
        $root = $this->createAccount($company, null);
        $levelTwo = $this->createAccount($company, $root);
        $levelThree = $this->createAccount($company, $levelTwo);
        $firstAccount = $this->createAccount($company, $levelThree);

        $secondAccount = $this->createAccount($company, $levelThree);
        $thirdAccount = $this->createAccount($company, $levelThree);

        $this->assertSame('1.1.01.000001', $firstAccount->code);
        $this->assertSame('1.1.01.000002', $secondAccount->code);
        $this->assertSame('1.1.01.000003', $thirdAccount->code);
    }

    public function test_it_indents_the_display_code_according_to_its_depth(): void
    {
        $account = new ChartOfAccount(['code' => '1.1.01.000001']);

        $this->assertSame(str_repeat("\u{00A0}", 6).'1.1.01.000001', $account->indented_code);
    }

    public function test_the_migration_normalizes_existing_codes_without_changing_the_hierarchy(): void
    {
        $company = Company::factory()->create();
        $root = $this->createLegacyAccount($company, null, '1');
        $levelTwo = $this->createLegacyAccount($company, $root, '1.1.1');
        $levelThree = $this->createLegacyAccount($company, $levelTwo, '1.1.1.1.1.1');
        $levelFour = $this->createLegacyAccount($company, $levelThree, '1.1.1.1.1.1.1.1.1.1');

        $migration = require database_path('migrations/2026_09_18_000000_normalize_chart_of_account_codes.php');
        $migration->up();

        $this->assertDatabaseHas('chart_of_accounts', ['uuid' => $root->uuid, 'code' => '1']);
        $this->assertDatabaseHas('chart_of_accounts', ['uuid' => $levelTwo->uuid, 'code' => '1.1']);
        $this->assertDatabaseHas('chart_of_accounts', ['uuid' => $levelThree->uuid, 'code' => '1.1.01']);
        $this->assertDatabaseHas('chart_of_accounts', ['uuid' => $levelFour->uuid, 'code' => '1.1.01.000001']);
    }

    public function test_every_seeded_account_uses_the_pattern_for_its_hierarchy_level(): void
    {
        $company = Company::factory()->create();

        $this->seed(ChartOfAccountSeeder::class);

        $accounts = ChartOfAccount::withoutGlobalScopes()
            ->where('company_id', $company->uuid)
            ->get();

        foreach ($accounts as $account) {
            $segments = explode('.', $account->code);

            foreach ($segments as $depth => $segment) {
                $expectedWidth = match ($depth) {
                    0, 1 => 1,
                    2 => 2,
                    default => 6,
                };

                $this->assertMatchesRegularExpression(
                    '/^\d{'.$expectedWidth.'}$/',
                    $segment,
                    "O código {$account->code} não respeita o nível {$depth}.",
                );
            }

            if ($account->parent_uuid) {
                $parent = $accounts->firstWhere('uuid', $account->parent_uuid);
                $this->assertNotNull($parent);
                $this->assertStringStartsWith($parent->code.'.', $account->code);
            }
        }

        $this->assertSame('1', $accounts->firstWhere('name', 'ATIVO')->code);
        $this->assertSame('1.1', $accounts->firstWhere('name', 'ATIVO CIRCULANTE')->code);
        $this->assertSame('1.1.01', $accounts->firstWhere('name', 'Caixa e Equivalentes de Caixa')->code);
        $this->assertSame('5.2.02.000004', $accounts->firstWhere('name', 'Material de Escritório')->code);
    }

    public function test_the_seeder_is_idempotent_and_does_not_duplicate_accounts(): void
    {
        $company = Company::factory()->create();

        $this->seed(ChartOfAccountSeeder::class);
        $firstCount = ChartOfAccount::withoutGlobalScopes()
            ->where('company_id', $company->uuid)
            ->count();

        $this->seed(ChartOfAccountSeeder::class);

        $this->assertSame(
            $firstCount,
            ChartOfAccount::withoutGlobalScopes()->where('company_id', $company->uuid)->count(),
        );
    }

    public function test_an_authenticated_user_creates_accounts_with_the_established_sequence(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->for($company)->create();
        $this->seed(ChartOfAccountSeeder::class);
        $parent = ChartOfAccount::withoutGlobalScopes()
            ->where('company_id', $company->uuid)
            ->where('code', '1.1.01')
            ->firstOrFail();

        foreach (['Caixa dinheiro', 'Banco Sicoob', 'Banco do Brasil'] as $index => $name) {
            $response = $this->actingAs($user, 'sanctum')->postJson('/api/chartOfAccounts', [
                'company_id' => $company->uuid,
                'parent_uuid' => $parent->uuid,
                'name' => $name,
                'type' => ChartOfAccount::TYPE_ASSET,
            ]);

            $response
                ->assertCreated()
                ->assertJsonPath('code', '1.1.01.'.str_pad((string) ($index + 1), 6, '0', STR_PAD_LEFT));
        }
    }

    private function createAccount(Company $company, ?ChartOfAccount $parent): ChartOfAccount
    {
        $code = ChartOfAccount::generateNextCode($company->uuid, $parent?->uuid);

        return ChartOfAccount::withoutGlobalScopes()->create([
            'company_id' => $company->uuid,
            'parent_uuid' => $parent?->uuid,
            'code' => $code,
            'name' => 'Conta '.$code,
            'type' => ChartOfAccount::TYPE_ASSET,
        ]);
    }

    private function createLegacyAccount(Company $company, ?ChartOfAccount $parent, string $code): ChartOfAccount
    {
        return ChartOfAccount::withoutGlobalScopes()->create([
            'company_id' => $company->uuid,
            'parent_uuid' => $parent?->uuid,
            'code' => $code,
            'name' => 'Conta antiga '.$code,
            'type' => ChartOfAccount::TYPE_ASSET,
        ]);
    }
}
