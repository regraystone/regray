<?php

use App\Data\ProjectFilterData;
use App\Repositories\Contracts\ProjectCategoryRepositoryInterface;
use App\Repositories\Contracts\ProjectRepositoryInterface;
use App\Repositories\Contracts\StoneRepositoryInterface;
use App\Services\ProjectService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Mockery;

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
