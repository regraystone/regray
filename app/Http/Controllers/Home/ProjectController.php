<?php

namespace App\Http\Controllers\Home;

use App\Actions\Home\ListProjectsAction;
use App\Actions\Home\ShowProjectAction;
use App\Data\ProjectFilterData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Home\ProjectIndexRequest;
use App\Http\Requests\Home\ProjectShowRequest;
use App\Http\Resources\ProjectCardResource;
use App\Http\Resources\ProjectProfileResource;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    public function __construct(
        public readonly ListProjectsAction $listProjects,
        public readonly ShowProjectAction $showProject,
    ) {}

    public function show(ProjectShowRequest $request): Response
    {
        $project = $this->showProject->execute($request->validated('project'));

        return Inertia::render('project-profile', [
            'project' => new ProjectProfileResource($project),
        ]);
    }

    public function index(ProjectIndexRequest $request): Response
    {
        $filters = ProjectFilterData::fromRequest($request);

        $result = $this->listProjects->execute($filters);

        return Inertia::render('home', [
            'projects' => Inertia::scroll(ProjectCardResource::collection($result['projects'])),
            'filters' => $result['filters'],
            'filterOptions' => Inertia::once(fn () => $result['filterOptions']),
        ]);
    }
}
