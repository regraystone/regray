<?php

namespace App\Http\Controllers\Home;

use App\Actions\Home\ListBrandsAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\BrandCardResource;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The home page: the hero and the brands. Each brand's page has its collection.
 */
class StoneController extends Controller
{
    public function __construct(
        public readonly ListBrandsAction $listBrands,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('home', [
            'brands' => Inertia::once(fn () => BrandCardResource::collection($this->listBrands->execute())->resolve($request)),
        ]);
    }
}
