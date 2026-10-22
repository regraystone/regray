# Quote "Update": edit a sent inquiry from the Review step

Update on a Quote card now opens the Inquire page at Review with that inquiry's projects. The client opens a project with Edit, changes it, comes back to Review and presses Save changes, which updates the same inquiry. No schema change.

## What changed

### Backend

- **Routes** (client-only, guarded by `quotations.view` and `inquiries.create`, registered above `quote/{reference}`):
    - `GET quote/{reference}/edit` (`quote.edit`) renders `inquire` with `mode: 'edit'`, `reference`, `projects` (`InquiryEditResource`) and the same catalogue as the Inquire page (`InquiryCatalogResource`, now shared with `ViewController::inquire`).
    - `PATCH quote/{reference}` (`quote.update`).
- **`InquiryEditController`**: `edit` → `ShowInquiryEditAction` (scoped through `InquiryDetailsService::forClient`, so another client's reference is a 404). When the status may not be updated, it redirects to `quote/{reference}` with an error toast carrying the reason.
- **`InquiryActions::updateRefusal()`** gives the reason, with `UPDATE_NOT_AVAILABLE` ("This inquiry can no longer be changed.") for paid and completed, where the button is hidden and so has no reason of its own.
- **`InquiryEditResource`**: real ids for projects and files, service and application slugs, stones as `{stoneId (slug), quantity, edge, finish}`, the site answers, `neededBy`/`neededByTime` from the schedule in Manila time (time only for visits), `deadlineLocked`/`deadlineLockedReason`, and `attachments` with `status: 'saved'`.
- **`UpdateInquiryRequest`** extends `StoreInquiryRequest`, so every create rule applies, and adds `projects.*.id` and `projects.*.keepFileIds` (kept plus new files may not exceed the per-project limit).
- **`UpdateInquiryAction` → `InquiryService::update()`**, in one transaction:
    - locks the inquiry row and asks `updateRefusal()` again at write time (a status that changed since the page loaded is refused as a `status` error);
    - the projects sent must be exactly the inquiry's projects, by id (anything else is refused as a `projects` error);
    - updates each project's fields and syncs its services, applications and stones;
    - moves the project's visit through `ScheduleService::updateForInquiryProject` (title, notes, arrival), and books one if an older project had none;
    - deletes the file rows not kept and stores new uploads with the `WrittenFiles` cleanup; the removed files are deleted from their disk only after the commit;
    - writes one `AuditEvent::Updated` row (new `AuditLogRepositoryInterface`), with each project's editable fields before and after. The status and the status history are untouched.
- **`ScheduleService`**: `arrivalForInquiryProject()` (a visit's own time, otherwise the time of day the schedule already has), `deadlineLockedReason()` (null only while the visit is `pending`) and `updateForInquiryProject()`.

### Frontend

- `pages/quote.tsx`: Update links to `quote.edit`; View details still goes to the details page.
- `pages/inquire.tsx` takes `mode`. In edit mode it:
    - starts on Review, with the form filled by `toEditFormProjects()`;
    - is titled "Update {reference}", with "Back to Quote" in the header, which returns to the Quote list;
    - hides Add and Remove and labels the send "Save changes" ("Nothing is saved until you save changes"), from `reviewActions()`;
    - saves with `sendInquiryRequest(url, values, 'patch')`, a POST with `_method=PATCH` carrying `projects[i][id]` and `projects[i][keepFileIds][k]`; errors go back on their projects through `mapInquiryErrors`, and success lands on the details page with the server's toast;
    - shows a locked deadline read-only with its reason in the requirements step;
    - refuses a commit that would split the project into several (picking a site visit beside a stone, or two visits).
- **Attachments** are now a union: an upload (`File`, `uploading`/`done`) or a stored file (`status: 'saved'`, `fileId`, name, size, type). `attachmentMeta()` reads either; the uploader, the review sheet, the schema and `buildInquiryFormData` handle both.

## Decisions

- **Drafts are off in edit mode.** No `edit:{reference}` key: the edit never reads, writes or clears the new-inquiry draft (`isRestored` stays false, so the autosave is inert). A refresh starts the edit over from the server.
- **Add and Remove stay out.** Editing changes existing projects only, and a commit that would add projects is refused in the browser and on the server. Adding or removing projects on a sent inquiry could come back later; it needs its own rules for schedules and history.
- **Locked deadline:** once a project's visit is no longer `pending` (the prompt said `requested`, which is now `pending`), its date and time are shown read-only with the reason, and the server refuses a change but accepts the same value back, so the rest of the project can still be edited.
- **Unchanged deadlines are still checked.** A deadline already in the past fails the "not in the past" rule on save, as it does for a new inquiry, so the client has to pick a new date. A locked deadline that is already in the past therefore cannot be saved; in practice the inquiry is quoted by then.
- **A project that is not a visit** keeps its schedule's time of day when only the date changes.

## Tests

- **Pest, `Quote/QuoteEditTest`:** the edit page for a client's own pending inquiry with real ids; quoted, paid, completed and cancelled redirect with the reason; another client's inquiry is a 404; Sales and Marketing get 403 on both routes; a save changes fields, replaces stones and services, moves the visit, writes an audit row and no status history; a confirmed visit cannot move but the rest saves; a project id from another inquiry is refused; removed files are deleted and new ones stored; a failure partway leaves nothing changed and no new files; a status quoted after the page loaded is refused.
- **node:test, `tests/js/inquiry-edit.test.mjs`:** `toEditFormProjects`, `deadlineLocks`, `reviewActions` in both modes, and `buildInquiryFormData` with ids and kept files.
- **Playwright, `inquiry/l-edit.spec.ts` (42):** Update → Review lists the project with no Add or Remove → Edit → rename → Back to Review → Save changes → the details page shows the new name.

# Schedule page on real data, and inquiry schedules

Branch `dev`. The Schedule page reads real schedules, and sending an inquiry books one schedule per project.

## What changed

### Data (migrations consolidated)

Still in development, so the migrations were rewritten rather than added to. Run `migrate:fresh --seed`.

- **One migration per table, and no altering migrations.** Every column, index, check constraint and foreign key of a table is in its create migration:
    - `users` has the two-factor columns (was `2025_08_14_…_add_two_factor_columns_to_users_table`).
    - `conversations` has `support_client_id`, `assigned_to`, `assigned_at` (with their checks and the `assigned_at` trigger) and `pool_notified_message_id`.
    - `messages` has `reply_to_id`, `client_key`, `system_event`, `system_user_id`, the `system` type check, the inquiry-card-once index and the `schedule_id` foreign key.
    - `conversation_members` has `role` and the read, delivered and notified message pointers with their foreign keys.
    - `stone_sizes`, `stone_thicknesses`, `stone_stone_category`, `stone_stone_size` and `stone_stone_thickness` each have their own file (was `create_stone_filter_tables`). The default sizes and thicknesses are still inserted; the backfill from existing stones is dropped, as a fresh database has none.
    - Removed: `000034_add_last_read_message_fk_to_conversation_members`, `000035_add_schedule_id_fk_to_messages`, the three `2026_10_20_…` migrations and `2026_10_21_…_add_reminder_markers_to_conversations_and_members`.
- **Renumbered so each table comes after the tables it points at:** `schedules` is now `000022` (before `inquiry_projects`, `000023`, which constrains `schedule_id` directly), and `messages` (`000030`) comes before `conversation_members` (`000031`). The tables in between moved up by one.
- **The one exception:** `conversations` and `messages` point at each other, so `conversations.pool_notified_message_id`'s foreign key is added at the end of the messages migration, once both tables exist.
- **Left grouped:** the files Laravel and Spatie ship (`users` with `password_reset_tokens` and `sessions`, `cache`, `jobs`, and the permission tables).
- `inquiry_projects` has no `deadline_date` or `deadline_time`; it has `schedule_id` (nullable, unique, `nullOnDelete`).
- `config/database.php`: the `pgsql` connection now sets the session `timezone` (`DB_TIMEZONE`, default `UTC`). Eloquent writes timestamps without an offset in the app's zone, so a Postgres server in another zone (the local one runs `Asia/Shanghai`) stored every timestamp 8 hours off. Production's Postgres image already runs in UTC.

### Backend

- **Schedule page:** `ScheduleIndexRequest` → `ListSchedulesAction` → `ScheduleService` → `ScheduleRepositoryInterface` / `EloquentScheduleRepository` (bound in `AppServiceProvider`) → `ScheduleResource` (`id`, `title`, `arrivalAt`, `status`, `notes`, `inquiryReference`; nothing about the client).
    - `ViewController::schedule()` serves `/schedule` and `/profile/schedule` with two lazy props: `schedules` (every status, `arrival_at` in `[from, to)`, by arrival) and `upcoming`.
    - `from`/`to` are ISO dates or datetimes; a value without an offset is read in Manila. Both or neither; `to` after `from`; at most 62 days (`schedule.max_range_days`). Without them the range is the six weeks the mini calendar shows for this month.
    - Visibility comes from the signed-in user: `isClient()` filters to `client_id = user`; every staff role sees all. A `client_id` in the URL is ignored.
    - **Upcoming:** the next three (`ListSchedulesAction::UPCOMING_LIMIT`) `pending` or `confirmed` visits with `arrival_at >= now - schedule.ongoing_minutes` (default 60), soonest first. Empty when there are none.
    - New `config/schedule.php`: `timezone` (Asia/Manila), `ongoing_minutes` (`SCHEDULE_ONGOING_MINUTES`) and `max_range_days`.
- **Inquiries:**
    - `InquiryService::addProject()` books the project's schedule through `ScheduleService::createForInquiryProject()` (client as `client_id` and `booked_by`, `inquiry_id`, `pending`, title = project name, notes "Inquiry {reference} · {project name} · {location}", so a visit says which of the inquiry's projects it is for), then writes the project with `schedule_id`. Both are inside `CreateInquiryAction`'s transaction.
    - `InquiryStatusService::transition()` to `cancelled` cancels the inquiry's pending and confirmed schedules in the same transaction (`cancelled_at` now, reason "Inquiry cancelled"). Completed ones are left alone.
    - `InquiryDetailsResource` reads `neededBy` and `neededByTime` ("Target deadline") from the linked schedule in Manila time.
    - `StoreInquiryRequest`: `neededBy` (the date) is required for every project. `neededByTime` is asked and required only when every service on the project is standalone (Site Ocular & Technical Assessment; Stone Relocation, Dismantling & Reinstallation; Post-Installation Quality Care), and must still be ahead in Manila time. Any other project's time is dropped and its schedule is booked for 10:00, the showroom's opening hour (`ScheduleService::DEFAULT_ARRIVAL_TIME`).
    - `InquiryDetailsResource` returns `neededByTime` only for those visit projects, so the details page never shows the default 10:00.
    - **Inquiry card in a thread:** the bold heading is the inquiry reference instead of the first project's name, and the line under it is the project count only ("2 projects"). `InquiryCardResource` no longer sends `title`.

### Frontend

- `pages/schedule.tsx` draws the `schedules` and `upcoming` props. Previous, Next, Today, the mini calendar's day and month, and the mobile day step call `router.get` with `only: ['schedules']`, `preserveState`, `preserveScroll` and `replace`, passing one range that covers the grid and the mini calendar's six weeks. When the two drift further apart than 62 days, the side that did not move follows the one that did. The grid dims and is `aria-busy` while loading.
- `components/schedule/upcoming-list.tsx` is one flat list of the `upcoming` prop, with no day headings ("Today", "Tomorrow"). Each row has a month-and-day tile, the title, the weekday and time, the status and the actions menu.
- **Week grid hours:** the grid shows 10:00–18:00 and widens to take in any visit on show outside those hours (`gridHours()` in `lib/schedule.ts`). Before, such a visit was silently left off the grid, which is why a project booked for the old 09:00 default did not appear.
- The event dialog shows the inquiry reference when there is one.
- The mock `data/schedule.ts` is removed. The types and the pure helpers (`toScheduleItem`, `rangeCovering`, `fitsRange`, `rangeQuery`, `gridHours`) are in `lib/schedule.ts`.
- **Inquire page:** every project asks for a date ("Target deadline"), required. Only a visit also asks for the time ("… at …"). `lib/inquiry-schema.ts` mirrors the PHP rule.
- `components/conversations/inquiry-card.tsx` shows the reference as the heading and `projectCountText()` (`lib/thread-cards.ts`, replacing `inquirySubline`) under it.
- `pages/schedule-book.tsx`: comment only (see TODO). Booking is unchanged.

### Seeders

- `InquirySeeder` books each project through `ScheduleService::createForInquiryProject()` and cancels a cancelled inquiry's schedules through `ScheduleService::cancelForInquiry()`. Every project in `seeders/data/inquiries.php` now has a date and a time.
- `ScheduleSeeder` no longer maps `apt-03`, `apt-04` and `apt-06` to inquiries. Its booking rows keep `inquiry_id` NULL and are looked up with it, so they never match an inquiry's schedule.

## Decisions

- **Date required for every project, time only for visits:** `arrival_at` is NOT NULL and every project becomes a schedule, so every project needs a date. A project that is not a visit is booked for 10:00 Manila (opening time) on that date. Changed in `StoreInquiryRequest` and `lib/inquiry-schema.ts` together.
- **Schedule status `requested` renamed to `pending`:** a schedule waits as `pending` until Marketing or Sales approve it. Statuses are `pending`, `confirmed`, `completed`, `cancelled`; the chat card reads "Booking pending". The approval step itself is not built yet.
- **"Now" window:** a visit counts as happening now for 60 minutes after its arrival (`schedule.ongoing_minutes`).
- **Cancelling an inquiry cancels its schedules** (pending and confirmed only), in the same transaction. `InquirySeeder` applies the same rule to seeded cancelled inquiries.
- **Range rule on the page:** one request covers the visible days and the mini calendar's six weeks, capped at 62 days.

## TODO list

- **`TODO(booking)`:** unchanged, plus: a booking creates a schedule with `client_id` = the client, `booked_by` = the client, `booked_by_type` = client, `inquiry_id` = NULL, status `pending`, and is never linked to an inquiry project. Marked in `Schedule` and `pages/schedule-book.tsx`.
- There are still two `schedule-status-badge.tsx` files (`components/` and `components/schedule/`). Not merged here.

## Tests

- **Pest:**
    - `Schedule/SchedulePageTest`: client scoping (a URL `client_id` is ignored), staff (sales, owner, admin) see all, the range filter (every status, `to` excluded, the default six weeks), the 62-day limit, upcoming (the next three open visits from an hour ago, soonest first; empty) and the resource shape for a booking and an inquiry visit.
    - `Inquiry/InquirySchedulesTest`: a sent inquiry shows as a card in the client's Regray Team chat and as a visit on the Schedule page (client and staff, in `schedules` and `upcoming`); one schedule per project on send (client, `inquiry_id`, `pending`, Manila arrival, `schedule_id`), and cancelling cancels open schedules.
    - Updated for the new columns and rules: `CreateInquiryActionTest` (a failed inquiry also leaves no schedule), `CreateInquiryEndToEndTest`, `StoreInquiryRequestTest` (a date required for every project, no time needed outside a visit), `QuoteDetailsTest` ("Target deadline" read from the schedule; a time only for a visit), `InquiryStatusCardTest` (the card sends no project name), `EloquentInquiryRepositoryTest`, `InquiryServiceTest`.
- `Home/CollectionFilterTest`: the stone filter backfill test now checks `Stone::attachPrimaryFilterOptions()`, which fills the same tables, since the backfill migration is gone.
- **node:test:** `tests/js/schedule.test.mjs`.
- **Playwright (not run):** the inquiry helpers fill a date for material projects (and the time only when the field is shown). The card specs in `message/d-cards.spec.ts` expect the reference as the heading and "N projects" under it.
- **Checks run:** `php artisan test` (828 tests), PHPStan, Pint, `npm run types:check`, `npm run check`, `npm test` (153 tests), and `migrate:fresh --seed` on the test database.

# Message page: revision 2

Branch `feat/messaging`. The Message page now runs on real data and updates live through Reverb.

## What changed

### Data (new migrations only)

These migrations were later folded into the create migrations (see the top section).

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

### Follow-up: reminders, limits and attachments

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
    - people search: 60 (`message-people`);
    - opening an attachment: 300 (`message-attachments`).
- **Attachments:** the paperclip sends photos (JPG, PNG, WebP), PDFs and Word documents (DOCX), up to 5 files, 10 MB each and 19 MB together (`messaging.attachments`, mirrored in `lib/message-attachments.ts`). A message may be files alone; its body is then empty, and the list reads "Sent a photo" or "Sent 3 files".
    - **Storage:** `s3-private` (the private bucket) when `APP_ENV=production`, Laravel's own storage (`storage/app/private`) otherwise. This does not follow `FILESYSTEM_PRIVATE_DISK`, so a production server missing that line still cannot keep files inside a container the next deploy replaces. `MESSAGE_ATTACHMENT_DISK` overrides it. They are stored as `messages/{conversation id}/{uuid}.{ext}`, and the sender's file name is kept only in the row.
    - **Serving:** `GET /message/attachments/{id}` is the only way to a file. It asks `ConversationPolicy::view`, the same as the thread. On S3 it redirects to a presigned link that expires after 5 minutes; locally it streams the file with `nosniff`.
    - **Removed messages:** a removed message's files stay on the disk, like its row, but are no longer listed or served (404).

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
