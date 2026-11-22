<?php

namespace App\Http\Controllers\Product;

use App\Http\Controllers\Controller;
use App\Http\Requests\Product\ProductStatusRequest;
use App\Http\Requests\Product\StoreFurnitureRequest;
use App\Http\Requests\Product\UpdateFurnitureRequest;
use App\Models\Furniture;
use App\Models\User;
use App\Services\Product\FurnitureAdminService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * The Products page's furniture writes: add, edit, turn on or off, archive.
 * Each route checks its own permission on top of the page's.
 *
 * `{id}` stays a plain id, looked up after the route's permission, so
 * nothing says whether one exists to someone who may not manage products.
 * An inactive one is managed here too; an archived one is gone from the
 * page, so it is a 404.
 */
class FurnitureAdminController extends Controller
{
    public function __construct(private readonly FurnitureAdminService $service) {}

    public function store(StoreFurnitureRequest $request): RedirectResponse
    {
        $thumbnail = $request->thumbnail();
        $piece = $this->service->create($request->furnitureData(), $thumbnail, $request->gallery(), self::actor($request), $request->ip(), $request->userAgent());

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name is added.', ['name' => $piece->name])]);

        return back();
    }

    public function update(UpdateFurnitureRequest $request, string $id): RedirectResponse
    {
        $piece = self::find($id);
        $this->service->update($piece, $request->furnitureData(), $request->thumbnail(), $request->gallery(), self::actor($request), $request->ip(), $request->userAgent());

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name is updated.', ['name' => $piece->name])]);

        return back();
    }

    public function status(ProductStatusRequest $request, string $id): RedirectResponse
    {
        $piece = self::find($id);
        $active = $request->boolean('active');
        $this->service->setActive($piece, $active, self::actor($request), $request->ip(), $request->userAgent());

        Inertia::flash('toast', ['type' => 'success', 'message' => $active
            ? __(':name is shown on Regray again.', ['name' => $piece->name])
            : __(':name is hidden from Regray.', ['name' => $piece->name])]);

        return back();
    }

    public function archive(Request $request, string $id): RedirectResponse
    {
        $piece = self::find($id);
        $this->service->archive($piece, self::actor($request), $request->ip(), $request->userAgent());

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name is archived.', ['name' => $piece->name])]);

        return back();
    }

    /** A piece still on the Products page, active or not, or a 404. */
    public static function find(string $id): Furniture
    {
        return ctype_digit($id)
            ? Furniture::query()->findOrFail((int) $id)
            : abort(404);
    }

    private static function actor(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
