# Message page: revision 2

Branch `feat/messaging`. The Message page now runs on real data and updates live through Reverb.

## What changed

### Data (new migrations only)

- `2026_10_20_000001_add_support_and_assignment_to_conversations`:
    - Adds the `support` conversation type, with `support_client_id` (unique: one Regray Team thread per client), `assigned_to` and `assigned_at`.
    - Check constraints tie them together.
    - A trigger clears `assigned_at` when deleting the assignee's account nulls `assigned_to`.
- `2026_10_20_000002_add_role_and_delivery_to_conversation_members`: adds a member `role` (`member`, `admin`, `viewer`) and `last_delivered_message_id`.
- `2026_10_20_000003_add_replies_keys_and_system_events_to_messages`:
    - Adds `reply_to_id`, `client_key` (unique per conversation, which dedupes retried sends) and the `system` message type with `system_event` and `system_user_id`.
    - Adds a partial unique index so that one inquiry status change posts one card.
- Composite-key `conversation_members` rows are written only through the query builder in `EloquentConversationMemberRepository`, never with `->save()`. The read and delivered pointers only move forward.

### Backend

- **Enums:**
    - `ConversationType::Support`, `MessageType::System`, `ConversationMemberRole` and `SystemEvent`.
    - The permissions `conversations.create` and `support.claim`, granted to Marketing (and listed on Admin).
- **Layering:** FormRequests → `app/Actions/Message` → `app/Services/Message` → repository interfaces → Eloquent repositories. The repositories are bound in `AppServiceProvider`.
- **`ConversationPolicy`** decides `view`, `send`, `claim`, `release` and `reassign`. The routes (`can:` middleware) and the broadcast channels both use it.
- **Endpoints** (all under `messages.view`):
    - `GET /message` (Inertia, with infinite scroll).
    - `GET /message/{id}/messages` (cursor paging with `before`/`after`, `hasMore`, pointers and the staff-only `assignment`).
    - Send (throttled to 60 a minute) and edit/remove.
    - Read and delivered pointers.
    - Claim, release and reassign.
    - People search and group creation (`conversations.create`).
- **Regray Team:**
    - Registration creates the client's thread (`CreateNewUser`). It is also created on first list load.
    - The thread is visible to the client, the assignee and, while unassigned, the whole pool.
    - Claims take a row lock and run a conditional update, so the second claim gets 409 with the winner's name.
    - Release and reassign write `system` rows.
- **Inquiry status cards:**
    - `InquiryStatusService::transition()` writes the status and history and dispatches `InquiryStatusChanged` after commit.
    - Sending an inquiry dispatches it too (from nothing to `pending`), so every inquiry posts a "New inquiry" card.
    - `PostInquiryStatusCard` (queued) posts one card per status: into the inquiry's group chat when it has one, otherwise into the client's Regray Team thread (created if missing). Until groups are bound (TODO(inquiry-binding)), every card goes to the Regray Team thread.
- **Realtime (Reverb):**
    - The private channels are `user.{id}`, `support.pool` and `conversation.{id}`.
    - `MessageCreated`, `MessageUpdated`, `PointerMoved`, `ConversationActivity`, `SupportThreadClaimed`, `SupportThreadReleased` and `GroupCreated`.
    - Typing uses client whispers.
    - The client's Reverb config is shared at runtime as the Inertia prop `realtime`, because Docker images bake in `.env.example`'s `VITE_*` values.
    - `docker-compose.yml` has a `reverb` service, and `docker/Caddyfile` proxies `/app/*` to it.
- **Privacy:**
    - Clients never get the `assignment` payload, the support client block, pool viewers' receipts or other clients' visit cards.
    - While a Regray Team thread is unassigned, staff senders are shown to the client as "Regray Team".
    - `ScheduleCardResource` names staff only on staff bookings, and sends the cancel reason only on cancelled cards.

### Frontend

- `pages/message.tsx` was rewritten on `components/conversations/*`:
    - The list, rows, thread, header, bubbles, composer, assignment card, typing indicator, receipts, date separators and the inquiry, schedule and system cards.
    - The new-group and reassign dialogs.
- `hooks/use-conversation-thread.ts` handles paging, optimistic sends with `clientKey`, live events and catch-up after a reconnect.
- The logic lives in pure libs, tested under `tests/js`:
    - `conversation-skeleton`, `thread-scroll`, `long-press`, `message-grouping`, `receipts`, `assignment` and `thread-cards`.
- The old mock data and components were removed (`data/conversations.ts`, `chat-panel`, `conversation-list`, …).
- Other pages:
    - "Message us" (inquiry result) opens the Regray Team thread.
    - "Message the team" (inquiry details) shows only when the inquiry has a group.

### Seeders

- `ConversationSeeder`:
    - Gives every client a Regray Team thread.
    - Leaves `client@example.com` with two unassigned messages, and gives a second client a thread claimed by `marketing@example.com` (with its system row).
    - Adds the admin to the sample groups so the admin sees them.
- `RolePermissionSeeder` grants Marketing the two new permissions, and lists them on Admin too, as the matrix lists every permission there.

### Dependencies

- Added `laravel/reverb ^1.12`, `laravel-echo` and `pusher-js`.
- Reverb needs Guzzle 7, so `guzzlehttp/guzzle`, `promises` and `psr7` were downgraded from 8 to 7 (`composer require -W --minimal-changes`). No other package moved.

### Follow-up: reminders and limits

- **Unread reminders (email):** `messages:send-reminders` runs every five minutes on the scheduler. A message waiting unread past `messaging.reminders.after_minutes` (`MESSAGE_REMINDER_AFTER_MINUTES`, default 30) sends an email. Who gets it:
    - **A member** (a client, the staff member handling a thread, a group member) gets `UnreadMessagesNotification`, about messages someone else sent them.
    - **The support pool** (everyone with `support.claim`) gets `ClientsWaitingNotification`, about clients waiting in Regray Team threads nobody has claimed.

    The rules:
    - Only people's text messages count. Cards, system lines, the reader's own messages and removed ones do not.
    - Each message is emailed about once. `conversation_members.last_notified_message_id` and `conversations.pool_notified_message_id` (new migration) record how far the emails have got.
    - The emails name the conversation and count the messages, never quote them.
    - Only verified addresses get them.
    - They are queued. The scheduler (`php artisan schedule:work` locally) and a queue worker must both run.

- **Rate limits** (per user, per minute):
    - send: 60;
    - read/delivered receipts: 120 (`message-receipts`);
    - claim, release and reassign: 10 (`message-assignment`);
    - people search: 60 (`message-people`).

## Decisions (revision 2)

- **Pool:** the Regray Team pool is everyone with `support.claim`, which is Marketing. Sales and Owner are not in it. Adding them is a one-line change to the matrix in `RolePermissionSeeder`.
- **Release:** the assignee and the admin can release a thread. Only the admin can reassign one.
- **What the client sees:** the client sees the assignee's name ("Handled by …") and the claim and release system messages.
- Staff see a Regray Team thread under the client's name, with the client's initials as its picture; the client sees it as "Regray Team" with the Regray logo. Staff cannot write in it until they claim it (409 "Assign this conversation to yourself first.").
- **Retention:** a removed message is soft-deleted and kept for good, shown as "Message removed". Nothing prunes these rows (see `Message`).
- The admin passes every policy ability through `Gate::before`, so the admin lists every Regray Team thread and can claim, release and reassign any of them. The admin still gets 409 when sending on a thread someone else handles.
- When a claim takes a thread, the rest of the pool loses it right away: reading it gets 403 and the thread leaves their lists live, over `support.pool`. A pool member who still tries to send gets 409 "{name} is handling this conversation." (changed after the E2E run; see `tests/e2e/message/REPORT.md`, "Fixes").

## TODO list

- **`TODO(inquiry-binding)`:** groups are not bound to inquiries yet.
    - Until they are, no real inquiry has a group, so status cards are not posted for real data and "Message the team" stays hidden.
    - Marked in `ConversationPolicy`, `PostInquiryStatusCard`, `InquiryStatusService`, `InquiryDetailsService`, `InquiryDetailsResource`, `GroupConversationService`, `ConversationSeeder` and `pages/inquiry-details.tsx`.
- **`TODO(booking)`:** post a `schedule_card` when a client books and on every later status change. It goes where inquiry cards go: the inquiry's group chat when there is one, otherwise the client's Regray Team thread. The card's rendering is already built. Marked in `Schedule`, `PostInquiryStatusCard` and `pages/schedule-book.tsx`.
- **`TODO(attachments)`:** the paperclip is disabled with a tooltip. `message_attachments` already exists. Marked in `components/conversations/composer.tsx`.
- **`TODO(staff-inquiry-scope)`:** the inquiry details page only finds the viewer's own inquiries (`InquiryDetailsService::forClient`), so a staff member who clicks an inquiry card gets a 404. Marked in `components/conversations/inquiry-card.tsx`.

## Tests

- **Pest, `tests/Feature/Message`:**
    - `ConversationPolicyTest`: the full role matrix.
    - `SupportThreadTest`: registration, idempotent creation, claim and the 409 for a second claim, visibility after a claim, the send 409s, release, reassign, `assignment` privacy, name masking, and the FK trigger.
    - `ThreadMessagesTest`: paging, `client_key` dedupe, edit and remove, and pointers.
    - `GroupConversationTest`.
    - `InquiryStatusCardTest`.
    - `BroadcastChannelsTest`.
    - `ScheduleCardTest`.
- **node:test, `tests/js`:** one file per pure lib listed above.
- **Checks run:** Pint, PHPStan, `php artisan test` (761 tests), `npm run check`, `npm run types:check`, `npm run test` (134 tests) and `migrate:fresh --seed`.

## Not verified

- **Truly simultaneous claims:** the race test sends its two claims one after the other. One winner is guaranteed by the `SELECT … FOR UPDATE` lock plus the `UPDATE … WHERE assigned_to IS NULL`, not by a parallel test.
- **"A non-assignee gets 409":** now tested with both the admin and another pool member (`SupportThreadTest`).
- **Docker:** the `reverb` service and the Caddy `/app/*` proxy were not run under Docker. Realtime was tested locally with `php artisan reverb:start`.
- **Manual browser checks:** claim, the live list update, "Handled by", typing, live delivery, the name label and "Seen" were all checked with two browsers (client and marketing). Not yet checked by hand:
    - release, reassign, edit, remove and reply in the browser (covered by the HTTP tests);
    - long-press on a phone emulator (the reducer is unit tested);
    - reconnect catch-up after restarting Reverb;
    - card sizes at 320, 390 and 1280 px.

    The Playwright end-to-end suite that follows covers these.

## Out-of-scope observations

- `composer test` takes longer than Composer's default 300 s process timeout on this machine, so it stops partway. `php artisan test` runs the suite without that limit. Not changed.
