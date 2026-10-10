<?php

namespace App\Http\Controllers\Home;

use App\Actions\Home\ListProjectsAction;
use App\Data\ProjectFilterData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Home\ProjectIndexRequest;
use App\Http\Resources\ProjectCardResource;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    public function __construct(
        public readonly ListProjectsAction $listProjects,
    ) {}

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
