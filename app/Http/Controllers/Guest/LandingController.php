<?php

namespace App\Http\Controllers\Guest;

use App\Actions\Home\ListBrandsAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\LandingBrandResource;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The landing page: Regray, the platform, introduced with its brands; signed-in
 * users redirect to their role home. Its News section is one general photo
 * that leads to the News page, so it reads no showcases.
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
            'brands' => Inertia::once(fn () => LandingBrandResource::collection($this->listBrands->execute())->resolve($request)),
        ]);
    }
}
