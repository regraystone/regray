<?php

use App\Enums\Permission;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
 * The Inbox page's three private channels. Each is authorized by the same
 * rule the HTTP endpoints use, so a thread a user may not open over HTTP is one
 * they may not listen to either.
 */

// The user's inbox: list-level nudges (a row changed, a group was added).
Broadcast::channel('user.{id}', fn (User $user, int $id): bool => $user->id === $id);

// The support pool: claims and releases of Regray Team threads, so a claimed
// thread leaves every other pool member's list at once.
Broadcast::channel('support.pool', fn (User $user): bool => ! $user->isClient() && $user->can(Permission::SupportClaim->value));

// One open thread: messages, edits, receipts, assignment changes and typing.
Broadcast::channel('conversation.{conversation}', fn (User $user, Conversation $conversation): bool => $user->can('view', $conversation));
