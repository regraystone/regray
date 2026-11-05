<?php

namespace App\Http\Controllers\Home;

use App\Actions\Home\ShowProjectAction;
use App\Actions\Stone\ShowStoneAction;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Resources\ProjectProfileResource;
use App\Http\Resources\StoneProfileResource;
use App\Models\Brand;
use App\Models\Stone;
use App\Services\ProjectService;
use App\Services\StoneService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StoneProfileController extends Controller
{
    public function __construct(
        public readonly ShowStoneAction $showStone,
        public readonly ShowProjectAction $showProject,
        public readonly StoneService $stoneService,
        public readonly ProjectService $projectService,
    ) {}

    /** The brand's own URL: the scoped binding has already matched the stone to this brand. */
    public function showForBrand(Brand $brand, Stone $stone): Response
    {
        return Inertia::render('stone-profile', [
            'stone' => new StoneProfileResource($this->showStone->execute($stone->slug)),
        ]);
    }

    /**
     * An old flat URL, at the root or under /dashboard/home. A stone has moved
     * under its brand, so it redirects there; a project still lives here.
     * Accessible to guests and signed-in users. For signed-in users, checks
     * permission; guests can see all published projects.
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
            // Not a stone, continue to check for project
        }

        // Try to find a published project
        try {
            $project = $this->projectService->findPublished($slug);

            // Guests can always see published projects, signed-in users need permission
            if ($request->user() !== null) {
                abort_unless($request->user()->can(Permission::HomeProjectsView->value), 403);
            }

            return Inertia::render('project-profile', [
                'project' => new ProjectProfileResource($project),
            ]);
        } catch (ModelNotFoundException) {
            // Not found at all
            abort(404);
        }
    }
}
