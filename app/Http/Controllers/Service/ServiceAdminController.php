<?php

namespace App\Http\Controllers\Service;

use App\Http\Controllers\Controller;
use App\Http\Requests\Service\SaveServiceTypeRequest;
use App\Http\Requests\Service\StoreServiceRequest;
use App\Http\Requests\Service\UpdateServiceRequest;
use App\Models\Service;
use App\Models\ServiceType;
use App\Models\User;
use App\Services\ServiceAdminService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * The admin's Services page writes: add, change and archive a service, and a
 * service type. The page itself is ViewController::services().
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

    public function archive(Request $request, string $id): RedirectResponse
    {
        $service = self::findService($id);
        $this->services->archive($service, self::actor($request), $request->ip(), $request->userAgent());

        return self::done(__(':name is archived.', ['name' => $service->name]));
    }

    public function storeType(SaveServiceTypeRequest $request): RedirectResponse
    {
        $type = $this->services->createType($request->typeData(), self::actor($request), $request->ip(), $request->userAgent());

        return self::done(__(':name is added.', ['name' => $type->name]));
    }

    public function updateType(SaveServiceTypeRequest $request, string $id): RedirectResponse
    {
        $type = self::findServiceType($id);
        $this->services->updateType($type, $request->typeData(), self::actor($request), $request->ip(), $request->userAgent());

        return self::done(__(':name is updated.', ['name' => $type->name]));
    }

    public function archiveType(Request $request, string $id): RedirectResponse
    {
        $type = $this->services->archiveType(self::findServiceType($id), self::actor($request), $request->ip(), $request->userAgent());

        return self::done(__(':name is archived.', ['name' => $type->name]));
    }

    /** A service still on the Services page, offered or not, or a 404. */
    public static function findService(string $id): Service
    {
        return ctype_digit($id)
            ? Service::query()->notArchived()->findOrFail((int) $id)
            : abort(404);
    }

    /** A service type still on the Services page, offered or not, or a 404. */
    public static function findServiceType(string $id): ServiceType
    {
        return ctype_digit($id)
            ? ServiceType::query()->notArchived()->findOrFail((int) $id)
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
