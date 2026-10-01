<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Support\CardboardMeasurements;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class ProductTechnicalSheetPdfController extends Controller
{
    public function __invoke(string $uuid)
    {
        $product = Product::query()
            ->with(['company', 'rawMaterials', 'productionSteps'])
            ->findOrFail($uuid);

        Gate::authorize('view', $product);

        $measurements = $product->cardboard_product_type === \App\Enums\CardboardProductType::Briefcase
            ? ($product->briefcase_measurements ?? [])
            : ($product->cardboard_measurements ?? []);

        $pdf = Pdf::loadView('pdf.product_technical_sheet', [
            'product' => $product,
            'lengthTotal' => $product->cardboard_product_type === \App\Enums\CardboardProductType::Briefcase
                ? \App\Support\BriefcaseMeasurements::lengthTotal($measurements)
                : CardboardMeasurements::lengthTotal($measurements),
            'widthTotal' => $product->cardboard_product_type === \App\Enums\CardboardProductType::Briefcase
                ? \App\Support\BriefcaseMeasurements::widthTotal($measurements)
                : CardboardMeasurements::widthTotal($measurements),
        ])->setPaper('a4', 'portrait');

        $name = Str::slug($product->name) ?: $product->uuid;

        return $pdf->stream("ficha-tecnica-{$name}.pdf");
    }
}
