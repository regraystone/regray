<?php

namespace App\Http\Controllers\Service;

use App\Http\Controllers\Controller;
use App\Http\Requests\Product\ProductStatusRequest;
use App\Http\Requests\Service\StoreServiceRequest;
use App\Http\Requests\Service\UpdateServiceRequest;
use App\Models\Service;
use App\Models\User;
use App\Services\ServiceAdminService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * The All services page's writes: add, change, show or hide, and archive a
 * service. The
 * page itself is ViewController::services(); service types and questions are
 * lists of their own (CatalogListController).
 *
 * `{id}` stays a plain id, looked up after the route's permission, so nothing
 * says whether one exists to someone who may not manage them. An archived one
 * is gone from the page, so it is a 404.
 */
class ServiceAdminController extends Controller
{
    public function __construct(private readonly ServiceAdminService $services) {}

    public function store(StoreServiceRequest $request): RedirectResponse
    {
        $service = $this->services->create($request->serviceData(), self::actor($request), $request->ip(), $request->userAgent());

        return self::done(__(':name is added.', ['name' => $service->name]));
    }

    public function update(UpdateServiceRequest $request, string $id): RedirectResponse
    {
        $service = self::findService($id);
        $this->services->update($service, $request->serviceData(), self::actor($request), $request->ip(), $request->userAgent());

        return self::done(__(':name is updated.', ['name' => $service->name]));
    }

    /** A row's switch: show the service on the Inquire page, or hide it. */
    public function status(ProductStatusRequest $request, string $id): RedirectResponse
    {
        $service = self::findService($id);
        $active = $request->boolean('active');
        $this->services->setActive($service, $active, self::actor($request), $request->ip(), $request->userAgent());

        return self::done($active
            ? __(':name is visible on the Inquire page again.', ['name' => $service->name])
            : __(':name is hidden from the Inquire page.', ['name' => $service->name]));
    }

    public function archive(Request $request, string $id): RedirectResponse
    {
        $service = self::findService($id);
        $this->services->archive($service, self::actor($request), $request->ip(), $request->userAgent());

        return self::done(__(':name is archived.', ['name' => $service->name]));
    }

    /** A service still on the Services page, offered or not, or a 404. */
    public static function findService(string $id): Service
    {
        return ctype_digit($id)
            ? Service::query()->notArchived()->findOrFail((int) $id)
            : abort(404);
    }

    private static function done(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }

    private static function actor(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
