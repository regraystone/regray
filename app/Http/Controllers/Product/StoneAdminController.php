<?php

namespace App\Http\Controllers\Product;

use App\Http\Controllers\Controller;
use App\Http\Requests\Product\ProductStatusRequest;
use App\Http\Requests\Product\StoreStoneRequest;
use App\Http\Requests\Product\UpdateStoneRequest;
use App\Models\Stone;
use App\Models\User;
use App\Services\Product\StoneAdminService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Inertia\Inertia;

/**
 * The Products page's stones writes: add, edit, turn on or off, archive.
 * Each route checks its own permission on top of the page's.
 *
 * `{id}` stays a plain id, looked up after the route's permission, so
 * nothing says whether one exists to someone who may not manage products.
 * An inactive one is managed here too; an archived one is gone from the
 * page, so it is a 404.
 */
class StoneAdminController extends Controller
{
    public function __construct(private readonly StoneAdminService $service) {}

    public function store(StoreStoneRequest $request): RedirectResponse
    {
        /** @var UploadedFile $thumbnail The request requires it. */
        $thumbnail = $request->thumbnail();
        $stone = $this->service->create($request->stoneData(), $thumbnail, $request->gallery(), self::actor($request), $request->ip(), $request->userAgent());

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name is added.', ['name' => $stone->name])]);

        return back();
    }

    public function update(UpdateStoneRequest $request, string $id): RedirectResponse
    {
        $stone = self::find($id);
        $this->service->update($stone, $request->stoneData(), $request->thumbnail(), $request->gallery(), self::actor($request), $request->ip(), $request->userAgent());

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name is updated.', ['name' => $stone->name])]);

        return back();
    }

    public function status(ProductStatusRequest $request, string $id): RedirectResponse
    {
        $stone = self::find($id);
        $active = $request->boolean('active');
        $this->service->setActive($stone, $active, self::actor($request), $request->ip(), $request->userAgent());

        Inertia::flash('toast', ['type' => 'success', 'message' => $active
            ? __(':name is shown on Regray again.', ['name' => $stone->name])
            : __(':name is hidden from Regray.', ['name' => $stone->name])]);

        return back();
    }

    public function archive(Request $request, string $id): RedirectResponse
    {
        $stone = self::find($id);
        $this->service->archive($stone, self::actor($request), $request->ip(), $request->userAgent());

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name is archived.', ['name' => $stone->name])]);

        return back();
    }

    /** A stone still on the Products page, active or not, or a 404. */
    public static function find(string $id): Stone
    {
        return ctype_digit($id)
            ? Stone::query()->findOrFail((int) $id)
            : abort(404);
    }

    private static function actor(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
