# Message page: E2E report

**Environment:**

- Branch `feat/messaging`, after commit 7a9888f.
- Windows 11 with local Chrome (`E2E_CHROME_PATH`). `playwright install` was not run.
- `globalSetup` refuses to run unless `APP_ENV` is local or testing. It also refuses a cached config, and refuses the dev DB `regray`.
- `globalSetup` resets the dedicated DB `regray-e2e` (`migrate:fresh --seed` + `E2EMessageSeeder`). It then starts its own `php artisan serve` (:8001), Reverb (:8081) and queue worker.
- Accounts:
    - From the existing seeders: `marketing@`, `sales@`, `admin@` and `client@example.com`.
    - From `E2EMessageSeeder`: marketing B, the list tester, and the fresh, card, direct and support clients.
    - Passwords come from `E2E_PASSWORD*` and default to UserFactory's.

**Update, 2026-10-21: all six bugs and the scenario 22 conflict are fixed in product code.** See "Fixes" at the end. Pest covers the fixes to BUG-5 and scenario 22. Since the fixes, the browser suite has not been run past scenario 2: a desktop run of the whole suite passed 1 and 2, then was stopped because the machine ran low on memory. The statuses below are the results from **before** the fixes. Rerun `npx playwright test tests/e2e/message --project=desktop` to confirm them.

**Status before the fixes:** every scenario has a result: 37 pass and 5 fail. All 5 failures come from product bugs or a spec conflict, listed below. A single run of all three projects keeps getting killed on this machine (about 8 GB RAM, often under 1 GB free), so the results come from runs of one section at a time against a freshly seeded DB. On 2026-10-20 I re-ran 6, 10, 12, 18–22 and 37–42 after the last harness fixes.

Two harness bugs were fixed in that last round. Neither was a product change:

- `support/stack.ts`: the PowerShell call that starts a server waited for its output pipe, and the server inherited that pipe. Global setup therefore hung after starting the app. The pid now goes through a file.
- `support/fixtures.ts`: `api()` used plain `fetch`. A successful `PATCH /quote/{ref}/cancel` answers with `back()` (302), and `fetch` repeats a PATCH on a 302, so the request hit `PATCH /message` (405). The cancel calls in 38 and 40 now stop at the redirect, which Inertia would receive as a 303.

Scenario 22 depends on the claim made in 18–21. Run it with them, not on its own.

## Results

| #            | Requirement  | Status                    | Note                                                                                                                 |
| ------------ | ------------ | ------------------------- | -------------------------------------------------------------------------------------------------------------------- |
| 1            | R2           | pass                      |                                                                                                                      |
| 2            | R6           | pass                      |                                                                                                                      |
| 3            | §6           | pass                      |                                                                                                                      |
| 4            | R6           | pass                      |                                                                                                                      |
| 5            | R3           | pass                      |                                                                                                                      |
| 6            | R3           | **fail**                  | BUG-2                                                                                                                |
| 7            | R3           | pass                      |                                                                                                                      |
| 8            | R3           | pass                      |                                                                                                                      |
| 9            | R4           | pass                      |                                                                                                                      |
| 10           | R4           | **fail**                  | BUG-6: the anchor moved 36 px (limit 2 px)                                                                           |
| 11           | R4           | pass                      |                                                                                                                      |
| 12           | R5           | pass (all three projects) | Screenshots at 1280, 390 and 320                                                                                     |
| 13           | R5           | pass (all three projects) |                                                                                                                      |
| 14–17        | R5/R5b       | pass                      |                                                                                                                      |
| 18–21        | §5           | pass                      |                                                                                                                      |
| 22           | §5/§6        | **fail**                  | Spec conflict: B gets 403, not 409, because §6 hides a claimed thread from the rest of the pool before the 409 check |
| 23–25        | §5           | pass                      |                                                                                                                      |
| 26–32, 34–36 | R10/§4       | pass                      |                                                                                                                      |
| 33           | R10 (mobile) | pass                      | Uses CDP `Input.dispatchTouchEvent` and `page.clock`                                                                 |
| 37           | §4           | **fail**                  | BUG-5: A's send returned 500 and B's `/read` returned 500 while Reverb was down. B did catch up without a reload.    |
| 38           | §4           | pass                      | Exactly one cancelled card after the worker ran                                                                      |
| 39–40        | R8/R9        | pass                      |                                                                                                                      |
| 41           | general      | pass (all three projects) |                                                                                                                      |
| 42           | general      | **fail**                  | BUG-1 only: `sales@` gets a 403 from `/broadcasting/auth`. No other console errors.                                  |

## Bugs (not fixed, per the instructions)

- **BUG-1 (scenarios 11 and 42):** the Message page joins `private-support.pool` for every non-client, but the channel only admits `support.claim` holders. Sales, technical and other staff therefore get a 403 from `/broadcasting/auth` in the console.
    - Cause: `resources/js/pages/message.tsx`, the `if (!isClient)` around `echo.private('support.pool')`.
- **BUG-2 (scenario 6):** no skeleton rows appear while the first page of the list loads. `conversations` is a regular (not deferred) prop, so the `conversations === undefined` branch never runs.
- **BUG-3 (seen in scenarios 18 and 19):** a pool member viewing an unassigned Regray Team thread sends `POST /message/{id}/read` in a loop, dozens of times in 14 s.
    - Cause: `pointers` leaves viewers out, so `read()` in `use-conversation-thread.ts` never finds its own pointer. Each response replaces `pointers`, which fires the read again.
    - Effect: it saturates the single-threaded server (requests of 10 s or more).
- **BUG-4 (scenarios 19 and 23):** after a claim takes a thread away from a pool member, their open thread keeps calling `/read` and catch-up `/messages?after=`, and gets a 403 each time.
- **BUG-5 (scenario 37; also the inquiry cancel in an earlier run of 38 with Reverb down):** when Reverb is unreachable, `ShouldBroadcastNow` events throw. The send (`POST …/messages`), `/read` and `PATCH /quote/{ref}/cancel` then return 500, although the data was saved.
- **BUG-6 (scenario 10):** after older messages are prepended, the first message moves by about 36 px, roughly the height of the top spinner, which is removed in the same update.

## Observations

- On a thread opened within the first moment, the first typing whisper is dropped if it is sent before the channel subscription succeeds. The 1.5 s throttle then delays the indicator. Scenario 27 waits for channel authorization before typing.
- Screenshots are in `test-results/message/{desktop,mobile,narrow}/`. They include `12-inquiry-cards-*` and `12-schedule-cards-*` at 1280, 390 and 320. Failure screenshots and traces are under `test-results/artifacts/`.

## Fixes (2026-10-21)

- **BUG-1:** the page joins `support.pool` only when the user holds `support.claim` (`usePermissions`), not for every non-client.
- **BUG-2:** `conversations` is now a deferred scroll prop, so the page's skeleton shows while the list loads. Scenario 6 now holds only the list's own partial request; the page visit is no longer held.
- **BUG-3:** `use-conversation-thread.ts` remembers the newest message it has already reported as read. A viewer whose own pointer the server never returns now posts once per message, not on every render.
- **BUG-4:** the first 403 on a catch-up or a read receipt marks the thread as lost. The thread then calls `onLostAccess` once and stops asking. Scenarios 19 and 23 allow that one 403 and fail on a second one on the same endpoint. Both entries left `KNOWN_BUGS`.
- **BUG-5:** every message broadcast event implements `ShouldRescue`. A broadcast that fails is reported and no longer answers the request with a 500. Covered by `tests/Feature/Message/BroadcastFailureTest.php`.
- **BUG-6:** the thread takes its scroll anchor again once the older-messages spinner is on screen, so the prepend keeps the first message where the reader last saw it.
- **Scenario 22:** `ConversationPolicy::send` lets the support pool through on a Regray Team thread. A pool member who lost the claim now gets 409 "{A} is handling this conversation." Reading stays 403. Staff outside the pool still get 403. Covered in `SupportThreadTest`.
