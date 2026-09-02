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

        $pdf = Pdf::loadView('pdf.product_technical_sheet', [
            'product' => $product,
            'lengthTotal' => CardboardMeasurements::lengthTotal($product->cardboard_measurements ?? []),
            'widthTotal' => CardboardMeasurements::widthTotal($product->cardboard_measurements ?? []),
        ])->setPaper('a4', 'portrait');

        $name = Str::slug($product->name) ?: $product->uuid;

        return $pdf->download("ficha-tecnica-{$name}.pdf");
    }
}
