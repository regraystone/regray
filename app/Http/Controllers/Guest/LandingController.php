<?php

namespace App\Http\Controllers\Guest;

use App\Actions\Home\ListBrandsAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\LandingBrandResource;
use App\Http\Resources\LandingProjectResource;
use App\Services\ProjectService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The landing page: Regray, the platform, introduced with its brands and the
 * latest finished projects; signed-in users redirect to their role home.
 */
class LandingController extends Controller
{
    /** How many finished projects the landing page features. */
    private const PROJECT_COUNT = 4;

    public function __construct(
        public readonly ListBrandsAction $listBrands,
        public readonly ProjectService $projects,
    ) {}

    public function index(Request $request): Response|RedirectResponse
    {
        if ($request->user()) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('landing', [
            'brands' => Inertia::once(fn () => LandingBrandResource::collection($this->listBrands->execute())->resolve($request)),
            'projects' => Inertia::once(fn () => LandingProjectResource::collection($this->projects->latestPublished(self::PROJECT_COUNT))->resolve($request)),
        ]);
    }
}
