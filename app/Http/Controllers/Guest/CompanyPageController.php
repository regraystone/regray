<?php

namespace App\Http\Controllers\Guest;

use App\Actions\Home\CompanyFactsAction;
use App\Actions\Home\ListBrandsAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\LandingBrandResource;
use App\Http\Resources\LandingProjectResource;
use App\Services\ProjectService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The company pages the public header's menu opens, for everyone, signed in
 * or not: the brands by category, the showroom, Regray's story and how to
 * reach the team. The words on them are in the pages; the brands, projects
 * and figures come from the catalog.
 */
class CompanyPageController extends Controller
{
    /** How many finished projects the showroom's gallery shows. */
    private const GALLERY_PROJECTS = 6;

    public function __construct(
        public readonly ListBrandsAction $listBrands,
        public readonly ProjectService $projects,
        public readonly CompanyFactsAction $facts,
    ) {}

    public function brands(Request $request): Response
    {
        return Inertia::render('company/brands', [
            'brands' => $this->brandsFor($request),
        ]);
    }

    public function showroom(Request $request): Response
    {
        return Inertia::render('company/showroom', [
            'projects' => LandingProjectResource::collection($this->projects->latestPublished(self::GALLERY_PROJECTS))->resolve($request),
        ]);
    }

    public function about(Request $request): Response
    {
        return Inertia::render('company/about', [
            'brands' => $this->brandsFor($request),
            'facts' => $this->facts->execute(),
        ]);
    }

    public function contact(): Response
    {
        return Inertia::render('company/contact');
    }

    /** @return array<int, array<string, mixed>> */
    private function brandsFor(Request $request): array
    {
        return LandingBrandResource::collection($this->listBrands->execute())->resolve($request);
    }
}
