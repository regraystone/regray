<?php

namespace App\Http\Controllers\Home;

use App\Actions\Home\ShowShowcaseAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Home\ShowcaseShowRequest;
use App\Http\Resources\ShowcaseProfileResource;
use Inertia\Inertia;
use Inertia\Response;

class ShowcaseController extends Controller
{
    public function __construct(
        public readonly ShowShowcaseAction $showShowcase,
    ) {}

    public function show(ShowcaseShowRequest $request): Response
    {
        $showcase = $this->showShowcase->execute($request->validated('stone'), $request->validated('showcase'));

        return Inertia::render('showcase-profile', [
            'showcase' => new ShowcaseProfileResource($showcase),
        ]);
    }
}
