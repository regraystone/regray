<?php

namespace App\Http\Controllers\Guest;

use App\Actions\Home\ListBrandsAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\LandingBrandResource;
use App\Http\Resources\LandingShowcaseResource;
use App\Services\ShowcaseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The landing page: Regray, the platform, introduced with its brands and the
 * latest finished showcases; signed-in users redirect to their role home.
 */
class LandingController extends Controller
{
    /** How many finished showcases the landing page's News tells. */
    private const SHOWCASE_COUNT = 3;

    public function __construct(
        public readonly ListBrandsAction $listBrands,
        public readonly ShowcaseService $showcases,
    ) {}

    public function index(Request $request): Response|RedirectResponse
    {
        if ($request->user()) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('landing', [
            'brands' => Inertia::once(fn () => LandingBrandResource::collection($this->listBrands->execute())->resolve($request)),
            'showcases' => Inertia::once(fn () => LandingShowcaseResource::collection($this->showcases->latestPublished(self::SHOWCASE_COUNT))->resolve($request)),
        ]);
    }
}
