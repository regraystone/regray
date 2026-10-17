<?php

namespace App\Http\Controllers\Saved;

use App\Actions\Home\ShowProjectAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Saved\ProjectShowRequest;
use App\Http\Resources\ProjectProfileResource;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    public function __construct(
        public readonly ShowProjectAction $showProject,
    ) {}

    public function show(ProjectShowRequest $request): Response
    {
        $project = $this->showProject->execute($request->validated('stone'), $request->validated('project'));

        return Inertia::render('project-profile', [
            'project' => new ProjectProfileResource($project),
        ]);
    }
}
