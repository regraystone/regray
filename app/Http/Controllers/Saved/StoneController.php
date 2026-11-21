<?php

namespace App\Http\Controllers\Saved;

use App\Actions\Stone\ShowStoneAction;
use App\Concerns\RendersStoneProfile;
use App\Http\Controllers\Controller;
use App\Http\Requests\Saved\StoneShowRequest;
use Inertia\Response;

class StoneController extends Controller
{
    use RendersStoneProfile;

    public function __construct(
        public readonly ShowStoneAction $showStone,
    ) {}

    public function show(StoneShowRequest $request): Response
    {
        return $this->renderStoneProfile($request, $this->showStone->execute($request->validated('stone')));
    }
}
