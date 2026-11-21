<?php

namespace App\Http\Controllers\Brand;

use App\Http\Controllers\Controller;
use App\Http\Requests\Brand\StoreBrandRequest;
use App\Http\Requests\Brand\UpdateBrandRequest;
use App\Models\Brand;
use App\Models\User;
use App\Services\BrandAdminService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Inertia\Inertia;

/**
 * The admin's Brands page writes: add a brand, change one, archive one. The
 * list itself is ViewController::brands().
 *
 * `{id}` stays a plain id, looked up after the route's permission, so nothing
 * says whether a brand exists to someone who may not manage them. It is not
 * the slug binding: that finds only a visible brand (Brand::resolveRouteBinding),
 * and a hidden one is managed here too. An archived brand is gone from the
 * page, so it is a 404.
 */
class BrandAdminController extends Controller
{
    public function __construct(private readonly BrandAdminService $brands) {}

    public function store(StoreBrandRequest $request): RedirectResponse
    {
        /** @var UploadedFile $logo The request requires it. */
        $logo = $request->logo();
        $brand = $this->brands->create($request->brandData(), $logo, $request->thumbnail(), self::actor($request), $request->ip(), $request->userAgent());

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name is added.', ['name' => $brand->name])]);

        return back();
    }

    public function update(UpdateBrandRequest $request, string $id): RedirectResponse
    {
        $brand = self::find($id);
        $this->brands->update($brand, $request->brandData(), $request->logo(), $request->thumbnail(), $request->removesThumbnail(), self::actor($request), $request->ip(), $request->userAgent());

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name is updated.', ['name' => $brand->name])]);

        return back();
    }

    public function archive(Request $request, string $id): RedirectResponse
    {
        $brand = self::find($id);
        $this->brands->archive($brand, self::actor($request), $request->ip(), $request->userAgent());

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name is archived.', ['name' => $brand->name])]);

        return back();
    }

    /** A brand still on the Brands page, hidden or not, or a 404. */
    public static function find(string $id): Brand
    {
        return ctype_digit($id)
            ? Brand::query()->notArchived()->findOrFail((int) $id)
            : abort(404);
    }

    private static function actor(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
