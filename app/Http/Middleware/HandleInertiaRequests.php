<?php

namespace App\Http\Middleware;

use App\Services\Message\ConversationService;
use App\Services\SavedFurnitureService;
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

        $canSeeSaved = $user && Gate::forUser($user)->allows('saved.view');
        $savedStoneSlugs = $canSeeSaved ? app(SavedStoneService::class)->slugsFor($user) : [];
        $savedFurnitureSlugs = $canSeeSaved ? app(SavedFurnitureService::class)->slugsFor($user) : [];

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
            'savedFurnitureSlugs' => $savedFurnitureSlugs,
            // The badge on the Message link. A closure, so a partial reload that
            // asks for nothing else (the layout's live refresh) runs only this.
            'unreadMessageCount' => fn (): int => $user !== null && Gate::forUser($user)->allows('messages.view')
                ? app(ConversationService::class)->unreadTotal($user)
                : 0,
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
