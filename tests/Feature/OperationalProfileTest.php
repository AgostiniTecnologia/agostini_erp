<?php

namespace Tests\Feature;

use App\Enums\OperationalProfile;
use App\Models\Company;
use App\Models\Product;
use App\Models\User;
use App\Services\OperationalProfileResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationalProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_uses_standard_profile_by_default(): void
    {
        $company = Company::factory()->create();

        $this->assertSame(OperationalProfile::Standard, $company->operational_profile);
        $this->assertSame('m', $company->length_unit->value);
        $this->assertSame('kg', $company->weight_unit->value);
        $this->assertSame('5.000', $company->fold_margin);
        $this->assertSame('60.000', $company->length_flap_default);
    }

    public function test_it_resolves_the_authenticated_company_profile(): void
    {
        $company = Company::factory()->create([
            'operational_profile' => OperationalProfile::CardboardPackaging,
        ]);
        $user = User::factory()->for($company)->create();

        $this->actingAs($user);

        $this->assertTrue(app(OperationalProfileResolver::class)->isCardboardPackaging());
    }

    public function test_request_data_cannot_override_the_authenticated_company_profile(): void
    {
        $company = Company::factory()->create([
            'operational_profile' => OperationalProfile::Standard,
        ]);
        $user = User::factory()->for($company)->create();

        $this->actingAs($user);
        request()->merge(['operational_profile' => OperationalProfile::CardboardPackaging->value]);

        $this->assertFalse(app(OperationalProfileResolver::class)->isCardboardPackaging());
    }

    public function test_unknown_profile_falls_back_to_standard(): void
    {
        $company = Company::factory()->create();
        Company::query()->whereKey($company->getKey())->update(['operational_profile' => 'unknown']);
        $user = User::factory()->for($company)->create();

        $this->actingAs($user->fresh());

        $this->assertSame(OperationalProfile::Standard, app(OperationalProfileResolver::class)->current());
    }

    public function test_all_non_cardboard_profiles_use_the_standard_behavior(): void
    {
        foreach (OperationalProfile::cases() as $profile) {
            if ($profile === OperationalProfile::CardboardPackaging) {
                continue;
            }

            $company = Company::factory()->create(['operational_profile' => $profile]);
            $user = User::factory()->for($company)->create();
            $this->actingAs($user);

            $this->assertFalse(app(OperationalProfileResolver::class)->isCardboardPackaging(), $profile->value);
        }
    }

    public function test_cardboard_measurements_are_persisted_and_reloaded(): void
    {
        $company = Company::factory()->create([
            'operational_profile' => OperationalProfile::CardboardPackaging,
        ]);
        $measurements = [
            'internal_length' => '1742',
            'internal_width' => '395',
            'internal_height' => '97',
            'left_flap' => '60',
            'sheet_length' => '1747',
            'top_flap' => '202.5',
        ];

        $product = Product::factory()->forCompany($company)->create([
            'cardboard_measurements' => $measurements,
        ]);

        $this->assertSame($measurements, $product->fresh()->cardboard_measurements);
    }

    public function test_cardboard_cut_measurements_use_the_company_configuration(): void
    {
        $company = Company::factory()->create([
            'operational_profile' => OperationalProfile::CardboardPackaging,
            'fold_margin' => 8,
            'length_flap_default' => 70,
        ]);
        $this->actingAs(User::factory()->for($company)->create());

        $product = Product::factory()->forCompany($company)->create([
            'cardboard_measurements' => [
                'internal_length' => '100',
                'internal_width' => '40',
                'internal_height' => '20',
            ],
        ]);

        $measurements = $product->fresh()->cardboard_measurements;
        $this->assertSame('70', $measurements['left_flap']);
        $this->assertSame('28', $measurements['left_height']);
        $this->assertSame('108', $measurements['sheet_length']);
        $this->assertSame('28', $measurements['top_flap']);
        $this->assertSame('36', $measurements['top_height']);
        $this->assertSame('48', $measurements['sheet_width']);
    }

    public function test_tenant_scope_prevents_measurements_from_appearing_in_another_company(): void
    {
        $firstCompany = Company::factory()->create([
            'operational_profile' => OperationalProfile::CardboardPackaging,
        ]);
        $secondCompany = Company::factory()->create([
            'operational_profile' => OperationalProfile::CardboardPackaging,
        ]);
        $product = Product::factory()->forCompany($firstCompany)->create([
            'cardboard_measurements' => ['internal_length' => '1742'],
        ]);
        $secondUser = User::factory()->for($secondCompany)->create();

        $this->actingAs($secondUser);

        $this->assertNull(Product::query()->find($product->getKey()));
    }

    public function test_changing_profile_preserves_existing_measurements(): void
    {
        $company = Company::factory()->create([
            'operational_profile' => OperationalProfile::CardboardPackaging,
        ]);
        $product = Product::factory()->forCompany($company)->create([
            'cardboard_measurements' => ['internal_length' => '1742'],
        ]);

        $company->update(['operational_profile' => OperationalProfile::Standard]);

        $this->assertSame(['internal_length' => '1742'], $product->fresh()->cardboard_measurements);
    }

    public function test_standard_company_cannot_persist_cardboard_measurements(): void
    {
        $company = Company::factory()->create([
            'operational_profile' => OperationalProfile::Standard,
        ]);
        $user = User::factory()->for($company)->create();

        $this->actingAs($user);

        $product = Product::factory()->forCompany($company)->create([
            'cardboard_measurements' => ['internal_length' => '1742'],
        ]);

        $this->assertNull($product->fresh()->cardboard_measurements);
    }

    public function test_profile_change_prevents_overwriting_preserved_measurements(): void
    {
        $company = Company::factory()->create([
            'operational_profile' => OperationalProfile::CardboardPackaging,
        ]);
        $product = Product::factory()->forCompany($company)->create([
            'cardboard_measurements' => ['internal_length' => '1742'],
        ]);
        $company->update(['operational_profile' => OperationalProfile::Standard]);
        $user = User::factory()->for($company)->create();

        $this->actingAs($user);
        $product->update(['cardboard_measurements' => ['internal_length' => '9999']]);

        $this->assertSame(['internal_length' => '1742'], $product->fresh()->cardboard_measurements);
    }
}
