<?php

namespace App\Http\Controllers\Brand;

use App\Http\Controllers\Controller;
use App\Services\FurnitureService;
use App\Services\StoneService;
use Illuminate\Http\RedirectResponse;

/**
 * Old product URLs, from before products were scoped to a brand. Each one only
 * knows the product's slug, so the brand comes from the product.
 */
class ProductRedirectController extends Controller
{
    public function __construct(
        public readonly StoneService $stoneService,
        public readonly FurnitureService $furnitureService,
    ) {}

    public function furniture(string $furniture): RedirectResponse
    {
        $product = $this->furnitureService->getActiveFurniture($furniture);

        return redirect()->route('brands.furniture.show', ['brand' => $product->brand->slug, 'furniture' => $product->slug], 301);
    }

    public function dashboardFurniture(string $furniture): RedirectResponse
    {
        $product = $this->furnitureService->getActiveFurniture($furniture);

        return redirect()->route('dashboard.brands.furniture.show', ['brand' => $product->brand->slug, 'furniture' => $product->slug], 301);
    }

    public function dashboardStone(string $stone): RedirectResponse
    {
        $product = $this->stoneService->getActiveStone($stone);

        return redirect()->route('dashboard.brands.stones.show', ['brand' => $product->brand->slug, 'stone' => $product->slug], 301);
    }
}
