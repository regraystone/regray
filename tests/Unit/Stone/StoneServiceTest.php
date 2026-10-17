<?php

use App\Data\StoneFilterData;
use App\Models\Stone;
use App\Repositories\Contracts\StoneRepositoryInterface;
use App\Services\StoneService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

test('an active stone is returned by its slug', function (): void {
    $repository = Mockery::mock(StoneRepositoryInterface::class);
    $stone = new Stone;

    $repository->shouldReceive('findActiveBySlug')->once()->with('arancia')->andReturn($stone);
    $service = new StoneService($repository);

    expect($service->getActiveStone('arancia'))->toBe($stone);

    Mockery::close();
});

test('a missing active stone throws a model not found exception', function (): void {
    $repository = Mockery::mock(StoneRepositoryInterface::class);

    $repository->shouldReceive('findActiveBySlug')->once()->with('arancia')->andReturnNull();
    $service = new StoneService($repository);

    expect(fn () => $service->getActiveStone('arancia'))->toThrow(ModelNotFoundException::class);

    Mockery::close();
});

test('search term is trimmed and escaped', function (): void {
    $stoneRepo = Mockery::mock(StoneRepositoryInterface::class);

    $paginator = Mockery::mock(LengthAwarePaginator::class);

    $stoneRepo->shouldReceive('paginate')
        ->once()
        ->andReturn($paginator);

    $stoneRepo->shouldReceive('filterOptions')->andReturn([
        'categories' => Collection::make(),
        'sizes' => Collection::make(),
        'thicknesses' => Collection::make(),
    ]);

    $service = new StoneService($stoneRepo);

    $filters = new StoneFilterData(
        search: '  test%_\\  ',
        category: null,
        size: null,
        thickness: null,
        page: 1,
    );

    $result = $service->listStones($filters);

    expect($result['filters']['search'])->toBe('test\\%\\_\\\\');

    Mockery::close();
});
