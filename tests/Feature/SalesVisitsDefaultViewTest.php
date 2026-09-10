<?php

namespace Tests\Feature;

use App\Enums\SalesVisitsDefaultView;
use App\Livewire\ScheduledVisitsMap;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SalesVisitsDefaultViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_uses_map_as_the_system_default(): void
    {
        $company = Company::factory()->create();

        $this->assertSame(SalesVisitsDefaultView::Map, $company->sales_visits_default_view);
    }

    public function test_my_visits_starts_with_the_company_list_preference(): void
    {
        $company = Company::factory()->create([
            'sales_visits_default_view' => SalesVisitsDefaultView::List,
        ]);
        $user = User::factory()->for($company)->create(['is_active' => true]);

        $this->actingAs($user);

        Livewire::test(ScheduledVisitsMap::class)
            ->assertSet('viewMode', SalesVisitsDefaultView::List->value)
            ->call('toggleView')
            ->assertSet('viewMode', SalesVisitsDefaultView::Map->value);
    }

    public function test_my_visits_falls_back_to_map_without_a_valid_preference(): void
    {
        $company = Company::factory()->create();
        Company::query()->whereKey($company->getKey())->update([
            'sales_visits_default_view' => 'unknown',
        ]);
        $user = User::factory()->for($company)->create(['is_active' => true]);

        $this->actingAs($user->fresh());

        Livewire::test(ScheduledVisitsMap::class)
            ->assertSet('viewMode', SalesVisitsDefaultView::Map->value);
    }
}
