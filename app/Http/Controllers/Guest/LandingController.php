<?php

namespace App\Http\Controllers\Guest;

use App\Actions\Home\ListBrandsAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\BrandCardResource;
use App\Http\Resources\LandingProjectResource;
use App\Http\Resources\LandingServiceTypeResource;
use App\Services\InquiryCatalogService;
use App\Services\ProjectService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The landing page: guests see the hero, the brands, the latest finished
 * projects and the services they can ask for; signed-in users redirect to
 * their role home.
 */
class LandingController extends Controller
{
    /** How many finished projects the landing page shows. */
    private const PROJECT_COUNT = 6;

    public function __construct(
        public readonly ListBrandsAction $listBrands,
        public readonly ProjectService $projects,
        public readonly InquiryCatalogService $catalog,
    ) {}

    public function index(Request $request): Response|RedirectResponse
    {
        if ($request->user()) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('landing', [
            'brands' => Inertia::once(fn () => BrandCardResource::collection($this->listBrands->execute())->resolve($request)),
            'projects' => Inertia::once(fn () => LandingProjectResource::collection($this->projects->latestPublished(self::PROJECT_COUNT))->resolve($request)),
            'serviceTypes' => Inertia::once(fn () => LandingServiceTypeResource::collection($this->catalog->serviceTypes())->resolve($request)),
        ]);
    }
}
