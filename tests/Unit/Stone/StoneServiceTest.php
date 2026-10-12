<?php

use App\Models\Stone;
use App\Repositories\Contracts\StoneRepositoryInterface;
use App\Services\StoneService;
use Illuminate\Database\Eloquent\ModelNotFoundException;

test('an active stone is returned through the matching repository path', function (?string $projectSlug): void {
    $repository = Mockery::mock(StoneRepositoryInterface::class);
    $stone = new Stone;

    if ($projectSlug === null) {
        $repository->shouldReceive('findActiveBySlug')->once()->with('onyx')->andReturn($stone);
        $repository->shouldNotReceive('findActiveInPublishedProject');
    } else {
        $repository->shouldReceive('findActiveInPublishedProject')->once()->with('onyx', $projectSlug)->andReturn($stone);
        $repository->shouldNotReceive('findActiveBySlug');
    }
    $service = new StoneService($repository);

    expect($service->getActiveStone('onyx', $projectSlug))->toBe($stone);

    Mockery::close();
})->with([null, 'ginza-one']);

test('a missing active stone throws a model not found exception through either path', function (?string $projectSlug): void {
    $repository = Mockery::mock(StoneRepositoryInterface::class);

    if ($projectSlug === null) {
        $repository->shouldReceive('findActiveBySlug')->once()->with('onyx')->andReturnNull();
    } else {
        $repository->shouldReceive('findActiveInPublishedProject')->once()->with('onyx', $projectSlug)->andReturnNull();
    }
    $service = new StoneService($repository);

    expect(fn () => $service->getActiveStone('onyx', $projectSlug))->toThrow(ModelNotFoundException::class);

    Mockery::close();
})->with([null, 'ginza-one']);
