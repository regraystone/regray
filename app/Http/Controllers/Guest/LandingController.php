<?php

namespace App\Http\Controllers\Guest;

use App\Actions\Home\ListBrandsAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\BrandCardResource;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The landing page: guests see the hero and the brands, signed-in users redirect to their role home.
 */
class LandingController extends Controller
{
    public function __construct(
        public readonly ListBrandsAction $listBrands,
    ) {}

    public function index(Request $request): Response|RedirectResponse
    {
        if ($request->user()) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('landing', [
            'brands' => Inertia::once(fn () => BrandCardResource::collection($this->listBrands->execute())->resolve($request)),
        ]);
    }
}
