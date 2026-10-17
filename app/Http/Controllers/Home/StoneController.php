<?php

namespace App\Http\Controllers\Home;

use App\Actions\Home\ListStonesAction;
use App\Data\StoneFilterData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Home\StoneIndexRequest;
use App\Http\Resources\StoneCardResource;
use Inertia\Inertia;
use Inertia\Response;

class StoneController extends Controller
{
    public function __construct(public readonly ListStonesAction $listStones) {}

    public function index(StoneIndexRequest $request): Response
    {
        $result = $this->listStones->execute(StoneFilterData::fromRequest($request));

        return Inertia::render('home', [
            'stones' => Inertia::scroll(StoneCardResource::collection($result['stones'])),
            'filters' => $result['filters'],
            'filterOptions' => Inertia::once(fn () => $result['filterOptions']),
        ]);
    }
}
