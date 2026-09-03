<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        Artisan::call('shield:generate --panel app --all -n');

        $roleSuperAdmin = Role::firstOrCreate(['name' => config('filament-shield.super_admin.name')]);
        $roleGerente = Role::firstOrCreate(['name' => 'Gerente', 'guard_name' => 'web']);
        $roleUsuario = Role::firstOrCreate(['name' => 'Usuário', 'guard_name' => 'web']);

        $roleProducao = Role::firstOrCreate(['name' => 'Produção', 'guard_name' => 'web']);
        $roleVendedor = Role::firstOrCreate(['name' => 'Vendedor', 'guard_name' => 'web']);
        $roleMotorista = Role::firstOrCreate(['name' => 'Motorista', 'guard_name' => 'web']);

        $allPermissions = Permission::pluck('name')->toArray();
        $roleSuperAdmin->syncPermissions($allPermissions);

        $gerentePermissions = Permission::where('name', 'not like', '%force_delete%')
            ->pluck('name')->toArray();
        $roleGerente->syncPermissions($gerentePermissions);

        $roleUsuario->syncPermissions([]);


        $this->call([
            CompanySeeder::class,
        ]);

        $company = Company::first();
        $company2 = Company::skip(1)->first();

        $createUser = function (Company $userCompany, string $name, string $username, Role $role): void {
            $user = User::firstOrCreate(
                ['username' => $username],
                [
                    'company_id' => $userCompany->uuid,
                    'name' => $name,
                    'password' => Hash::make('password'),
                    'is_active' => true,
                ],
            );

            $user->assignRole($role);
        };

        $createUser($company, 'Super Admin User', 'root', $roleSuperAdmin);
        $createUser($company, 'Gerente', 'gerente', $roleGerente);
        $createUser($company, 'Usuário Vendedor', 'vendedor', $roleVendedor);
        $createUser($company, 'Usuario Produção', 'producao', $roleProducao);
        $createUser($company, 'Usuario Motorista', 'motorista', $roleMotorista);
        $createUser($company2, 'Usuario de outra Empresa', 'outro', $roleMotorista);

        $this->call([
            PauseReasonSeeder::class,
            HolidaySeeder::class,
            ClientSeeder::class,

            ChartOfAccountSeeder::class,
            FinancialTransactionSeeder::class,

            ProductSeeder::class,
            ProductionStepSeeder::class,
            ProductionProcessSeeder::class,
            ProductionOrderSeeder::class,

            SalesGoalSeeder::class,
            SalesVisitSeeder::class,
            SalesOrderSeeder::class,

            VehicleSeeder::class
        ]);
    }
}
