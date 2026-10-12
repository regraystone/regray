<?php

namespace App\Http\Controllers;

use App\Actions\Stone\ShowStoneAction;
use App\Http\Requests\Stone\StoneShowRequest;
use App\Http\Resources\StoneProfileResource;
use Inertia\Inertia;
use Inertia\Response;

class StoneProfileController extends Controller
{
    public function __construct(
        public readonly ShowStoneAction $showStone,
    ) {}

    public function show(StoneShowRequest $request): Response
    {
        $stone = $this->showStone->execute($request->validated('stone'));

        return Inertia::render('stone-profile', [
            'stone' => new StoneProfileResource($stone),
        ]);
    }

    public function showInProject(StoneShowRequest $request): Response
    {
        $projectSlug = $request->validated('project');
        $stone = $this->showStone->execute($request->validated('stone'), $projectSlug);

        return Inertia::render('stone-profile', [
            'stone' => new StoneProfileResource($stone),
            'projectSlug' => $projectSlug,
        ]);
    }
}
