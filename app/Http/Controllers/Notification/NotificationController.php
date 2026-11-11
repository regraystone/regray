<?php

namespace App\Http\Controllers\Notification;

use App\Http\Controllers\Controller;
use App\Http\Requests\Notification\NotificationPageRequest;
use App\Http\Resources\NotificationResource;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\Notification\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The workspace bell, as JSON: a page of the reader's notifications, opening
 * one, and marking them all read. A notification is only ever its
 * recipient's: anyone else's is not found.
 */
class NotificationController extends Controller
{
    public function __construct(
        public readonly NotificationService $notificationService,
    ) {}

    public function index(NotificationPageRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $page = $this->notificationService->page($user, $request->beforeId());

        return response()->json([
            'notifications' => NotificationResource::collection($page['notifications'])->resolve($request),
            'hasMore' => $page['hasMore'],
            'unreadCount' => $this->notificationService->unreadCount($user),
        ]);
    }

    /** Read the notification, and say where it leads: `url` is null when it is no longer available. */
    public function open(Request $request, UserNotification $notification): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($notification->user_id === $user->id, 404);

        $url = $this->notificationService->open($user, $notification);

        return response()->json([
            'url' => $url,
            'unreadCount' => $this->notificationService->unreadCount($user),
        ]);
    }

    public function readAll(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->notificationService->markAllRead($user);

        return response()->json(['unreadCount' => 0]);
    }
}
