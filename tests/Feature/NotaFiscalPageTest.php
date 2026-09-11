<?php

namespace Tests\Feature;

use App\Filament\Pages\NotaFiscal;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotaFiscalPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_invoice_navigation_points_directly_to_the_official_nfse_emitter(): void
    {
        $this->assertSame(
            'https://www.nfse.gov.br/EmissorNacional/Login',
            NotaFiscal::getNavigationUrl(),
        );
    }

    public function test_accounting_user_is_redirected_to_the_official_nfse_emitter(): void
    {
        config(['app.env' => 'local']);

        $company = Company::factory()->create();
        $user = User::factory()->for($company)->create(['is_active' => true]);
        $user->assignRole('Contábil');

        $this->actingAs($user)
            ->get('/app/nota-fiscal')
            ->assertRedirect('https://www.nfse.gov.br/EmissorNacional/Login');
    }

    public function test_user_without_accounting_role_cannot_access_the_invoice_page(): void
    {
        config(['app.env' => 'local']);

        $company = Company::factory()->create();
        $user = User::factory()->for($company)->create(['is_active' => true]);

        $this->actingAs($user)
            ->get('/app/nota-fiscal')
            ->assertForbidden();
    }

    public function test_removing_accounting_role_disables_access(): void
    {
        config(['app.env' => 'local']);

        $company = Company::factory()->create();
        $user = User::factory()->for($company)->create(['is_active' => true]);
        $user->assignRole('Contábil');
        $user->removeRole('Contábil');

        $this->actingAs($user->fresh())
            ->get('/app/nota-fiscal')
            ->assertForbidden();
    }

    public function test_super_admin_keeps_access_to_the_invoice_page(): void
    {
        config(['app.env' => 'local']);

        $company = Company::factory()->create();
        $user = User::factory()->for($company)->create(['is_active' => true]);
        Role::findOrCreate(config('filament-shield.super_admin.name'), 'web');
        $user->assignRole(config('filament-shield.super_admin.name'));

        $this->actingAs($user)
            ->get('/app/nota-fiscal')
            ->assertRedirect('https://www.nfse.gov.br/EmissorNacional/Login');
    }
}
