<?php

namespace App\Http\Controllers\Project;

use App\Http\Controllers\Controller;
use App\Http\Requests\Project\StoreProjectRequest;
use App\Http\Requests\Project\UpdateProjectRequest;
use App\Models\Project;
use App\Models\User;
use App\Services\ProjectAdminService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Inertia\Inertia;

/**
 * The admin's Projects page writes: add a project, change one, archive one.
 * The list itself is ViewController::projects().
 *
 * `{id}` stays a plain id, looked up after the route's permission, so nothing
 * says whether a project exists to someone who may not manage them. It is not
 * the slug a public page is found by, which finds only a published project;
 * a draft is managed here too. An archived project is gone from the page, so
 * it is a 404.
 */
class ProjectAdminController extends Controller
{
    public function __construct(private readonly ProjectAdminService $projects) {}

    public function store(StoreProjectRequest $request): RedirectResponse
    {
        /** @var UploadedFile $thumbnail The request requires it. */
        $thumbnail = $request->thumbnail();
        $project = $this->projects->create($request->projectData(), $thumbnail, $request->gallery(), self::actor($request), $request->ip(), $request->userAgent());

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name is added.', ['name' => $project->name])]);

        return back();
    }

    public function update(UpdateProjectRequest $request, string $id): RedirectResponse
    {
        $project = self::find($id);
        $this->projects->update($project, $request->projectData(), $request->thumbnail(), $request->gallery(), self::actor($request), $request->ip(), $request->userAgent());

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name is updated.', ['name' => $project->name])]);

        return back();
    }

    public function archive(Request $request, string $id): RedirectResponse
    {
        $project = self::find($id);
        $this->projects->archive($project, self::actor($request), $request->ip(), $request->userAgent());

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name is archived.', ['name' => $project->name])]);

        return back();
    }

    /** A project still on the Projects page, a draft or not, or a 404. */
    public static function find(string $id): Project
    {
        return ctype_digit($id)
            ? Project::query()->findOrFail((int) $id)
            : abort(404);
    }

    private static function actor(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
