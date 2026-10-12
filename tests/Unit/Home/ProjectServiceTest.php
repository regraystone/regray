<?php

use App\Data\ProjectFilterData;
use App\Models\Project;
use App\Repositories\Contracts\ProjectCategoryRepositoryInterface;
use App\Repositories\Contracts\ProjectRepositoryInterface;
use App\Repositories\Contracts\StoneRepositoryInterface;
use App\Services\ProjectService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

test('search term is trimmed and escaped', function (): void {
    $projectRepo = Mockery::mock(ProjectRepositoryInterface::class);
    $stoneRepo = Mockery::mock(StoneRepositoryInterface::class);
    $categoryRepo = Mockery::mock(ProjectCategoryRepositoryInterface::class);

    $paginator = Mockery::mock(LengthAwarePaginator::class);

    $projectRepo->shouldReceive('paginate')
        ->once()
        ->andReturn($paginator);

    $stoneRepo->shouldReceive('activeFilterOptions')->andReturn(Collection::make());
    $categoryRepo->shouldReceive('activeFilterOptions')->andReturn(Collection::make());

    $service = new ProjectService($projectRepo, $stoneRepo, $categoryRepo);

    $filters = new ProjectFilterData(
        search: '  test%_\\  ',
        stone: null,
        category: null,
        page: 1,
    );

    $result = $service->listProjects($filters);

    expect($result['filters']['search'])->toBe('test\\%\\_\\\\');

    Mockery::close();
});

test('a published project is returned from the repository', function (): void {
    $projectRepo = Mockery::mock(ProjectRepositoryInterface::class);
    $stoneRepo = Mockery::mock(StoneRepositoryInterface::class);
    $categoryRepo = Mockery::mock(ProjectCategoryRepositoryInterface::class);
    $project = new Project;

    $projectRepo->shouldReceive('findPublishedBySlug')
        ->once()
        ->with('ginza-one')
        ->andReturn($project);

    $service = new ProjectService($projectRepo, $stoneRepo, $categoryRepo);

    expect($service->getPublishedProject('ginza-one'))->toBe($project);

    Mockery::close();
});

test('a missing published project throws a model not found exception', function (): void {
    $projectRepo = Mockery::mock(ProjectRepositoryInterface::class);
    $stoneRepo = Mockery::mock(StoneRepositoryInterface::class);
    $categoryRepo = Mockery::mock(ProjectCategoryRepositoryInterface::class);

    $projectRepo->shouldReceive('findPublishedBySlug')
        ->once()
        ->with('no-such-project')
        ->andReturnNull();

    $service = new ProjectService($projectRepo, $stoneRepo, $categoryRepo);

    expect(fn () => $service->getPublishedProject('no-such-project'))
        ->toThrow(ModelNotFoundException::class);

    Mockery::close();
});
