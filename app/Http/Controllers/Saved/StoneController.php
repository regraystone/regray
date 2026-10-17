<?php

namespace App\Http\Controllers\Saved;

use App\Actions\Stone\ShowStoneAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Saved\StoneShowRequest;
use App\Http\Resources\StoneProfileResource;
use Inertia\Inertia;
use Inertia\Response;

class StoneController extends Controller
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
}
