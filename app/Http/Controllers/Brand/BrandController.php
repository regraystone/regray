<?php

namespace App\Http\Controllers\Brand;

use App\Actions\Brand\ShowBrandAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Brand\BrandShowRequest;
use App\Http\Resources\BrandProfileResource;
use Inertia\Inertia;
use Inertia\Response;

class BrandController extends Controller
{
    public function __construct(
        public readonly ShowBrandAction $showBrand,
    ) {}

    public function show(BrandShowRequest $request): Response
    {
        $brand = $this->showBrand->execute($request->validated('brand'));

        return Inertia::render('brand-profile', [
            'brand' => new BrandProfileResource($brand),
        ]);
    }
}
