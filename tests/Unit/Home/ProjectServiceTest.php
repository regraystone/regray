<?php

use App\Models\Project;
use App\Repositories\Contracts\ProjectRepositoryInterface;
use App\Services\ProjectService;
use Illuminate\Database\Eloquent\ModelNotFoundException;

test('a published project is returned from the repository', function (): void {
    $projectRepo = Mockery::mock(ProjectRepositoryInterface::class);
    $project = new Project;

    $projectRepo->shouldReceive('findPublishedInStone')
        ->once()
        ->with('arctic-pink', 'ginza-one')
        ->andReturn($project);

    $service = new ProjectService($projectRepo);

    expect($service->getPublishedProject('arctic-pink', 'ginza-one'))->toBe($project);

    Mockery::close();
});

test('a missing published project throws a model not found exception', function (): void {
    $projectRepo = Mockery::mock(ProjectRepositoryInterface::class);

    $projectRepo->shouldReceive('findPublishedInStone')
        ->once()
        ->with('arctic-pink', 'no-such-project')
        ->andReturnNull();

    $service = new ProjectService($projectRepo);

    expect(fn () => $service->getPublishedProject('arctic-pink', 'no-such-project'))
        ->toThrow(ModelNotFoundException::class);

    Mockery::close();
});
