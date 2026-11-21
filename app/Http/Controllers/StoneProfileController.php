<?php

namespace App\Http\Controllers;

use App\Actions\Stone\ShowStoneAction;
use App\Concerns\RendersStoneProfile;
use App\Http\Requests\Stone\StoneShowRequest;
use Inertia\Response;

class StoneProfileController extends Controller
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
