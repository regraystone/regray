<?php

namespace App\Http\Controllers\Home;

use App\Actions\Home\ShowProjectAction;
use App\Actions\Stone\ShowStoneAction;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Home\StoneShowRequest;
use App\Http\Resources\ProjectProfileResource;
use App\Http\Resources\StoneProfileResource;
use App\Services\ProjectService;
use App\Services\StoneService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
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

    public function show(StoneShowRequest $request): Response
    {
        $stone = $this->showStone->execute($request->validated('stone'));

        return Inertia::render('stone-profile', [
            'stone' => new StoneProfileResource($stone),
        ]);
    }

    /**
     * Show a resource by slug, resolving to either a stone profile or project profile.
     * Tries stone first, then project, then 404. Accessible to guests and signed-in users.
     * For signed-in users, checks permission; for guests, allows access to all active stones and published projects.
     */
    public function showFlat(Request $request, string $slug): Response
    {
        // Try to find an active stone first
        try {
            $stone = $this->stoneService->getActiveStone($slug);

            // Guests can always see active stones, signed-in users need permission
            if ($request->user() !== null) {
                abort_unless($request->user()->can(Permission::HomeStoneView->value), 403);
            }

            return Inertia::render('stone-profile', [
                'stone' => new StoneProfileResource($stone),
            ]);
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
