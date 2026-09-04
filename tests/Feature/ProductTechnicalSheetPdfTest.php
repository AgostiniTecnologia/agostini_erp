<?php

namespace Tests\Feature;

use App\Enums\OperationalProfile;
use App\Models\Company;
use App\Models\Permission;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductTechnicalSheetPdfTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_user_can_preview_product_technical_sheet(): void
    {
        $company = Company::factory()->create([
            'operational_profile' => OperationalProfile::CardboardPackaging,
        ]);
        $user = User::factory()->for($company)->create();
        Permission::findOrCreate('view_product', 'web');
        $user->givePermissionTo('view_product');
        $product = Product::factory()->forCompany($company)->create([
            'name' => 'Caixa Modelo A',
            'cardboard_measurements' => [
                'left_flap' => '60',
                'left_height' => '102',
                'sheet_length' => '1747',
                'right_height' => '102',
                'right_flap' => '60',
                'top_flap' => '202.5',
                'top_height' => '107',
                'sheet_width' => '400',
                'bottom_height' => '107',
                'bottom_flap' => '202.5',
            ],
        ]);

        $response = $this->actingAs($user)->get(route('products.technical-sheet.pdf', $product->uuid));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $response->assertHeader('content-disposition', 'inline; filename=ficha-tecnica-caixa-modelo-a.pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_user_cannot_preview_product_from_another_company(): void
    {
        $firstCompany = Company::factory()->create();
        $secondCompany = Company::factory()->create();
        $user = User::factory()->for($secondCompany)->create();
        Permission::findOrCreate('view_product', 'web');
        $user->givePermissionTo('view_product');
        $product = Product::factory()->forCompany($firstCompany)->create();

        $this->actingAs($user)
            ->get(route('products.technical-sheet.pdf', $product->uuid))
            ->assertNotFound();
    }
}
