<?php

namespace App\Http\Middleware;

use App\Services\SavedStoneService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        $savedStoneSlugs = $user && Gate::forUser($user)->allows('saved.view')
            ? app(SavedStoneService::class)->slugsFor($user)
            : [];

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user,
                'permissions' => $user?->getAllPermissions()->pluck('name')->values()->all() ?? [],
                'roles' => $user?->getRoleNames()->values()->all() ?? [],
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'savedStoneSlugs' => $savedStoneSlugs,
            'realtime' => $user === null || config('broadcasting.default') !== 'reverb'
                ? null
                : [
                    'key' => config('broadcasting.client.key'),
                    'host' => config('broadcasting.client.host') ?: null,
                    'port' => config('broadcasting.client.port') ? (int) config('broadcasting.client.port') : null,
                    'scheme' => config('broadcasting.client.scheme') ?: null,
                ],
        ];
    }
}
