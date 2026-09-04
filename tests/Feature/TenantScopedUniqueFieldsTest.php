<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\Product;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Tests\TestCase;

class TenantScopedUniqueFieldsTest extends TestCase
{
    use RefreshDatabase;

    public function test_companies_can_use_the_same_product_sku(): void
    {
        $firstCompany = Company::factory()->create();
        $secondCompany = Company::factory()->create();

        Product::factory()->forCompany($firstCompany)->create(['sku' => 'SKU-1']);
        $secondProduct = Product::factory()->forCompany($secondCompany)->create(['sku' => 'SKU-1']);

        $this->assertSame('SKU-1', $secondProduct->sku);
    }

    public function test_a_product_sku_remains_unique_inside_the_same_company(): void
    {
        $company = Company::factory()->create();
        Product::factory()->forCompany($company)->create(['sku' => 'SKU-1']);

        $this->expectException(QueryException::class);

        Product::factory()->forCompany($company)->create(['sku' => 'SKU-1']);
    }

    public function test_companies_can_use_the_same_client_unique_fields(): void
    {
        $firstCompany = Company::factory()->create();
        $secondCompany = Company::factory()->create();
        $fields = [
            'taxNumber' => '12.345.678/0001-90',
            'state_registration' => 'ISENTO-1',
            'email' => 'cliente@example.com',
        ];

        $this->createClient($firstCompany, $fields);
        $secondClient = $this->createClient($secondCompany, $fields);

        $this->assertSame($fields['taxNumber'], $secondClient->taxNumber);
        $this->assertSame($fields['state_registration'], $secondClient->state_registration);
        $this->assertSame($fields['email'], $secondClient->email);
    }

    public function test_client_unique_fields_are_validated_inside_the_same_company(): void
    {
        $company = Company::factory()->create();
        $this->createClient($company, ['taxNumber' => '12.345.678/0001-90']);

        $sameCompany = Validator::make(
            ['taxNumber' => '12.345.678/0001-90'],
            ['taxNumber' => Rule::unique('clients', 'taxNumber')->where('company_id', $company->uuid)],
        );
        $otherCompany = Company::factory()->create();
        $differentCompany = Validator::make(
            ['taxNumber' => '12.345.678/0001-90'],
            ['taxNumber' => Rule::unique('clients', 'taxNumber')->where('company_id', $otherCompany->uuid)],
        );

        $this->assertTrue($sameCompany->fails());
        $this->assertFalse($differentCompany->fails());
    }

    private function createClient(Company $company, array $attributes): Client
    {
        return Client::query()->create(array_merge([
            'company_id' => $company->uuid,
            'name' => 'Cliente',
            'social_name' => 'Cliente LTDA',
            'taxNumber' => fake()->unique()->numerify('##############'),
        ], $attributes));
    }
}
