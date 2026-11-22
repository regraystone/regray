<?php

namespace App\Concerns;

use App\Http\Resources\LandingShowcaseResource;
use App\Http\Resources\StoneCardResource;
use App\Http\Resources\StoneProfileResource;
use App\Models\Stone;
use App\Services\StoneService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A stone's page, the same through each of its doors (the brand's URL, the
 * Saved one and the old flat one): the stone, the stones before and after it
 * of its brand and type, more of that type, and the showcases it was used in.
 */
trait RendersStoneProfile
{
    /** How many other stones of the same type the page shows. */
    private const RELATED_STONES = 4;

    /** How many of the showcases it was used in the page shows. */
    private const STONE_SHOWCASES = 3;

    private function renderStoneProfile(Request $request, Stone $stone): Response
    {
        $around = app(StoneService::class)->surroundings($stone, self::RELATED_STONES, self::STONE_SHOWCASES);
        $step = fn (?Stone $other): ?array => $other === null ? null : ['slug' => $other->slug, 'name' => $other->name];

        return Inertia::render('stone-profile', [
            'stone' => new StoneProfileResource($stone),
            'previous' => $step($around['previous']),
            'next' => $step($around['next']),
            'related' => StoneCardResource::collection($around['related'])->resolve($request),
            'showcases' => LandingShowcaseResource::collection($around['showcases'])->resolve($request),
        ]);
    }
}
