<?php

namespace App\Http\Controllers\Home;

use App\Actions\Home\ShowShowcaseAction;
use App\Actions\Stone\ShowStoneAction;
use App\Concerns\RendersStoneProfile;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Resources\ShowcaseProfileResource;
use App\Models\Brand;
use App\Models\Stone;
use App\Services\ShowcaseService;
use App\Services\StoneService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StoneProfileController extends Controller
{
    use RendersStoneProfile;

    public function __construct(
        public readonly ShowStoneAction $showStone,
        public readonly ShowShowcaseAction $showShowcase,
        public readonly StoneService $stoneService,
        public readonly ShowcaseService $showcaseService,
    ) {}

    /** The brand's own URL: the scoped binding has already matched the stone to this brand. */
    public function showForBrand(Request $request, Brand $brand, Stone $stone): Response
    {
        return $this->renderStoneProfile($request, $this->showStone->execute($stone->slug));
    }

    /**
     * An old flat URL, at the root or under /dashboard/home. A stone has moved
     * under its brand, so it redirects there; a showcase still lives here.
     * Accessible to guests and signed-in users. For signed-in users, checks
     * permission; guests can see all published showcases.
     */
    public function showFlat(Request $request, string $slug): Response|RedirectResponse
    {
        try {
            $stone = $this->stoneService->getActiveStone($slug);

            return redirect()->route(
                $request->routeIs('home.show') ? 'dashboard.brands.stones.show' : 'brands.stones.show',
                ['brand' => $stone->brand->slug, 'stone' => $stone->slug],
                301,
            );
        } catch (ModelNotFoundException) {
            // Not a stone, continue to check for showcase
        }

        // Try to find a published showcase
        try {
            $showcase = $this->showcaseService->findPublished($slug);

            // Guests can always see published showcases, signed-in users need permission
            if ($request->user() !== null) {
                abort_unless($request->user()->can(Permission::HomeShowcasesView->value), 403);
            }

            return Inertia::render('showcase-profile', [
                'showcase' => new ShowcaseProfileResource($showcase),
            ]);
        } catch (ModelNotFoundException) {
            // Not found at all
            abort(404);
        }
    }
}
