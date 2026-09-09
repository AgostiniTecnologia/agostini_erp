<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Mockery;
use Tests\TestCase;

class TransportReportLogoTest extends TestCase
{
    use RefreshDatabase;

    public function test_transport_report_passes_authenticated_company_to_pdf(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->for($company)->create();
        $pdf = Mockery::mock(\Barryvdh\DomPDF\PDF::class);

        Pdf::shouldReceive('loadView')
            ->once()
            ->with('reports.transporte_relatorio_pdf', Mockery::on(
                fn (array $data): bool => isset($data['company']) && $data['company']->is($company),
            ))
            ->andReturn($pdf);
        $pdf->shouldReceive('setPaper')->once()->with('a4', 'portrait')->andReturnSelf();
        $pdf->shouldReceive('stream')->once()->with('relatorio-transporte.pdf')->andReturn(response('pdf'));

        $this->actingAs($user)
            ->get(route('transporte.relatorio.pdf'))
            ->assertOk();
    }

    public function test_transport_report_uses_authenticated_web_route(): void
    {
        $route = Route::getRoutes()->getByName('transporte.relatorio.pdf');

        $this->assertSame('relatorio/transporte/pdf', $route->uri());
        $this->assertContains('web', $route->gatherMiddleware());
        $this->assertContains('auth', $route->gatherMiddleware());
    }
}
