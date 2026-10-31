<?php

namespace App\Http\Controllers\Saved;

use App\Actions\Saved\ListSavedStonesAction;
use App\Actions\Saved\SaveStoneAction;
use App\Actions\Saved\UnsaveStoneAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Saved\SavedIndexRequest;
use App\Http\Requests\Saved\SavedStoneRequest;
use App\Http\Resources\StoneCardResource;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SavedStoneController extends Controller
{
    public function __construct(
        public readonly ListSavedStonesAction $listSavedStones,
        public readonly SaveStoneAction $saveStone,
        public readonly UnsaveStoneAction $unsaveStone,
    ) {}

    public function index(SavedIndexRequest $request): Response
    {
        $result = $this->listSavedStones->execute($request->user());

        return Inertia::render('saved', [
            'savedStones' => StoneCardResource::collection($result['saved']),
        ]);
    }

    public function store(SavedStoneRequest $request): RedirectResponse
    {
        $this->saveStone->execute($request->user(), $request->validated('stone'));

        return back();
    }

    public function destroy(SavedStoneRequest $request): RedirectResponse
    {
        $this->unsaveStone->execute($request->user(), $request->validated('stone'));

        return back();
    }
}
