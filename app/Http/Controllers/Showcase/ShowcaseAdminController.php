<?php

namespace App\Http\Controllers\Showcase;

use App\Http\Controllers\Controller;
use App\Http\Requests\Showcase\StoreShowcaseRequest;
use App\Http\Requests\Showcase\UpdateShowcaseRequest;
use App\Models\Showcase;
use App\Models\User;
use App\Services\ShowcaseAdminService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Inertia\Inertia;

/**
 * The admin's Showcase page writes: add a showcase, change one, archive one.
 * The list itself is ViewController::showcases().
 *
 * `{id}` stays a plain id, looked up after the route's permission, so nothing
 * says whether a showcase exists to someone who may not manage them. It is not
 * the slug a public page is found by, which finds only a published showcase;
 * a draft is managed here too. An archived showcase is gone from the page, so
 * it is a 404.
 */
class ShowcaseAdminController extends Controller
{
    public function __construct(private readonly ShowcaseAdminService $showcases) {}

    public function store(StoreShowcaseRequest $request): RedirectResponse
    {
        /** @var UploadedFile $thumbnail The request requires it. */
        $thumbnail = $request->thumbnail();
        $showcase = $this->showcases->create($request->showcaseData(), $thumbnail, $request->gallery(), self::actor($request), $request->ip(), $request->userAgent());

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name is added.', ['name' => $showcase->name])]);

        return back();
    }

    public function update(UpdateShowcaseRequest $request, string $id): RedirectResponse
    {
        $showcase = self::find($id);
        $this->showcases->update($showcase, $request->showcaseData(), $request->thumbnail(), $request->gallery(), self::actor($request), $request->ip(), $request->userAgent());

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name is updated.', ['name' => $showcase->name])]);

        return back();
    }

    public function archive(Request $request, string $id): RedirectResponse
    {
        $showcase = self::find($id);
        $this->showcases->archive($showcase, self::actor($request), $request->ip(), $request->userAgent());

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name is archived.', ['name' => $showcase->name])]);

        return back();
    }

    /** A showcase still on the Showcase page, a draft or not, or a 404. */
    public static function find(string $id): Showcase
    {
        return ctype_digit($id)
            ? Showcase::query()->findOrFail((int) $id)
            : abort(404);
    }

    private static function actor(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
