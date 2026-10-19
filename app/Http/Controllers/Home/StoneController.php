<?php

namespace App\Http\Controllers\Home;

use App\Actions\Home\ListProjectsAction;
use App\Actions\Home\ListStonesAction;
use App\Data\ProjectFilterData;
use App\Data\StoneFilterData;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Home\ProjectIndexRequest;
use App\Http\Requests\Home\StoneIndexRequest;
use App\Http\Resources\ProjectCardResource;
use App\Http\Resources\StoneCardResource;
use App\Repositories\Eloquent\ProjectRepository;
use App\Repositories\Eloquent\StoneRepository;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StoneController extends Controller
{
    public function __construct(
        public readonly ListStonesAction $listStones,
        public readonly ListProjectsAction $listProjects,
        public readonly StoneRepository $stoneRepository,
        public readonly ProjectRepository $projectRepository,
    ) {}

    public function index(Request $request): Response
    {
        $collection = $request->query('collection', 'stones');

        if ($collection === 'projects' && $request->user()?->can(Permission::HomeProjectsView->value)) {
            return $this->indexProjects(app(ProjectIndexRequest::class));
        }

        return $this->indexStones(app(StoneIndexRequest::class));
    }

    private function indexStones(StoneIndexRequest $request): Response
    {
        $filterData = StoneFilterData::fromRequest($request);

        $result = $this->listStones->execute($filterData);

        $stoneFilterOptions = $result['filterOptions'];

        $allFilterOptions = [
            ...$stoneFilterOptions,
            ...($request->user()?->can(Permission::HomeProjectsView->value)
                ? ['projectCategories' => $this->projectRepository->filterOptions()['categories']]
                : []),
        ];

        return Inertia::render('home', [
            'stones' => Inertia::scroll(StoneCardResource::collection($result['stones'])),
            'filters' => array_merge($result['filters'], ['collection' => 'stones']),
            'filterOptions' => Inertia::once(fn () => $allFilterOptions),
        ]);
    }

    private function indexProjects(ProjectIndexRequest $request): Response
    {
        $filterData = ProjectFilterData::fromRequest($request);

        $result = $this->listProjects->execute($filterData);

        $stoneFilterOptions = $this->stoneRepository->filterOptions();
        $projectFilterOptions = $result['filterOptions'];

        $allFilterOptions = [
            ...$stoneFilterOptions,
            'projectCategories' => $projectFilterOptions['categories'],
        ];

        return Inertia::render('home', [
            'projects' => Inertia::scroll(ProjectCardResource::collection($result['projects'])),
            'filters' => array_merge($result['filters'], ['collection' => 'projects']),
            'filterOptions' => Inertia::once(fn () => $allFilterOptions),
        ]);
    }
}
