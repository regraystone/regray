<?php

namespace App\Http\Controllers\Saved;

use App\Actions\Home\ShowShowcaseAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Saved\ShowcaseShowRequest;
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
        // A saved stone's showcase stays, as history, after it is archived.
        $showcase = $this->showShowcase->execute($request->validated('stone'), $request->validated('showcase'), withArchived: true);

        return Inertia::render('showcase-profile', [
            'showcase' => new ShowcaseProfileResource($showcase),
        ]);
    }
}
