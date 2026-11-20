<?php

use App\Enums\Permission;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
 * The Inbox page's three private channels. Each is authorized by the same
 * rule the HTTP endpoints use, so a thread a user may not open over HTTP is one
 * they may not listen to either, and only for an account that may still sign
 * in (EnsureAccountActive signs a turned-off one out before this runs too).
 *
 * A channel is authorized when it is joined, and again whenever the browser
 * joins it again (a reconnect re-subscribes, and so asks here again), never
 * in between. So nothing on these channels is content: they carry ids and
 * nudges, and the reader fetches what they name over HTTP, where access is
 * checked on every request. Whoever loses access stops getting content at
 * once, whatever connection they still hold.
 */

// The user's inbox: list-level nudges (a row changed, a group was added).
Broadcast::channel('user.{id}', fn (User $user, int $id): bool => $user->canSignIn() && $user->id === $id);

// The support pool: claims and releases of Regray Team threads, so a claimed
// thread leaves every other pool member's list at once.
Broadcast::channel('support.pool', fn (User $user): bool => $user->canSignIn() && ! $user->isClient() && $user->can(Permission::SupportClaim->value));

// One open thread: messages announced by id, receipts, assignment changes and typing.
Broadcast::channel('conversation.{conversation}', fn (User $user, Conversation $conversation): bool => $user->canSignIn() && $user->can('view', $conversation));
