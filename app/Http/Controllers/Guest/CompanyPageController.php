<?php

namespace App\Http\Controllers\Guest;

use App\Actions\Home\CompanyFactsAction;
use App\Actions\Home\ListBrandsAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\LandingBrandResource;
use App\Http\Resources\LandingShowcaseResource;
use App\Services\ShowcaseService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The company pages the public header's menu opens, for everyone, signed in
 * or not: the brands by category, the showroom, Regray's story and how to
 * reach the team. The words on them are in the pages; the brands, showcases
 * and figures come from the catalog.
 */
class CompanyPageController extends Controller
{
    /** How many finished showcases the showroom's gallery shows. */
    private const GALLERY_SHOWCASES = 6;

    /** How many finished showcases the News page tells. */
    private const NEWS_STORIES = 12;

    public function __construct(
        public readonly ListBrandsAction $listBrands,
        public readonly ShowcaseService $showcases,
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
            'showcases' => LandingShowcaseResource::collection($this->showcases->latestPublished(self::GALLERY_SHOWCASES))->resolve($request),
        ]);
    }

    public function about(Request $request): Response
    {
        return Inertia::render('company/about', [
            'brands' => $this->brandsFor($request),
            'facts' => $this->facts->execute(),
        ]);
    }

    /**
     * News: the latest finished showcases told as stories, beside the Regray
     * Journal and the social accounts. The system holds no other news.
     */
    public function news(Request $request): Response
    {
        return Inertia::render('company/news', [
            'stories' => LandingShowcaseResource::collection($this->showcases->latestPublished(self::NEWS_STORIES))->resolve($request),
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
