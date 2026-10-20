# Message page: E2E report (in progress)

**Environment:**

- Branch `feat/messaging`, after commit 7a9888f.
- Windows 11 with local Chrome (`E2E_CHROME_PATH`). `playwright install` was not run.
- `globalSetup` refuses to run unless `APP_ENV` is local or testing. It also refuses a cached config, and refuses the dev DB `regray-studio`.
- `globalSetup` resets the dedicated DB `regray-studio-e2e` (`migrate:fresh --seed` + `E2EMessageSeeder`). It then starts its own `php artisan serve` (:8001), Reverb (:8081) and queue worker.
- Accounts:
    - From the existing seeders: `marketing@`, `sales@`, `admin@` and `client@example.com`.
    - From `E2EMessageSeeder`: marketing B, the list tester, and the fresh, card, direct and support clients.
    - Passwords come from `E2E_PASSWORD*` and default to UserFactory's.

**Status:** this run is not finished. The status column holds the last result of each scenario. Sections G–I must be re-run after the Reverb-restart fix in `support/stack.ts`. Earlier runs of G–I were invalid, because a Reverb restarted inside a test died when Playwright replaced the failing test's worker. A full run of all three projects was started after that fix, but it was stopped partway because the machine ran low on memory, and it recorded no results. Run `npx playwright test tests/e2e/message` again, ideally with fewer other programs open.

## Results

| #            | Requirement   | Status                                      | Note                                                                                                                 |
| ------------ | ------------- | ------------------------------------------- | -------------------------------------------------------------------------------------------------------------------- |
| 1            | R2            | pass                                        |                                                                                                                      |
| 2            | R6            | pass                                        |                                                                                                                      |
| 3            | §6            | pass                                        |                                                                                                                      |
| 4            | R6            | pass                                        |                                                                                                                      |
| 5            | R3            | pass                                        |                                                                                                                      |
| 6            | R3            | **fail**                                    | BUG-2                                                                                                                |
| 7            | R3            | pass                                        |                                                                                                                      |
| 8            | R3            | pass                                        |                                                                                                                      |
| 9            | R4            | pass                                        |                                                                                                                      |
| 10           | R4            | **fail**                                    | BUG-6: the anchor moved 36 px (limit 2 px)                                                                           |
| 11           | R4            | pass                                        |                                                                                                                      |
| 12           | R5            | pass (desktop, narrow); mobile needs re-run | The mobile run hit a login 429 before sign-ins were cached                                                           |
| 13           | R5            | pass (all three projects)                   |                                                                                                                      |
| 14–17        | R5/R5b        | pass                                        |                                                                                                                      |
| 18–21        | §5            | pass                                        |                                                                                                                      |
| 22           | §5/§6         | **fail**                                    | Spec conflict: B gets 403, not 409, because §6 hides a claimed thread from the rest of the pool before the 409 check |
| 23–25        | §5            | pass                                        |                                                                                                                      |
| 26–32, 34–36 | R10/§4        | pass                                        |                                                                                                                      |
| 33           | R10 (mobile)  | pass                                        | Uses CDP `Input.dispatchTouchEvent` and `page.clock`                                                                 |
| 37           | §4            | **fail**                                    | BUG-5. B did catch up without a reload.                                                                              |
| 38           | §4            | needs re-run                                | Last valid attempt: cancel returned 500 with Reverb down (BUG-5)                                                     |
| 39–41        | R8/R9/general | needs re-run                                |                                                                                                                      |
| 42           | general       | needs re-run                                | Runs strict. Expected to fail on BUG-1 at least.                                                                     |

## Bugs (not fixed, per the instructions)

- **BUG-1 (scenarios 11 and 42):** the Message page joins `private-support.pool` for every non-client, but the channel only admits `support.claim` holders. Sales, technical and other staff therefore get a 403 from `/broadcasting/auth` in the console.
    - Cause: `resources/js/pages/message.tsx`, the `if (!isClient)` around `echo.private('support.pool')`.
- **BUG-2 (scenario 6):** no skeleton rows appear while the first page of the list loads. `conversations` is a regular (not deferred) prop, so the `conversations === undefined` branch never runs.
- **BUG-3 (seen in scenarios 18 and 19):** a pool member viewing an unassigned Regray Team thread sends `POST /message/{id}/read` in a loop, dozens of times in 14 s.
    - Cause: `pointers` leaves viewers out, so `read()` in `use-conversation-thread.ts` never finds its own pointer. Each response replaces `pointers`, which fires the read again.
    - Effect: it saturates the single-threaded server (requests of 10 s or more).
- **BUG-4 (scenarios 19 and 23):** after a claim takes a thread away from a pool member, their open thread keeps calling `/read` and catch-up `/messages?after=`, and gets a 403 each time.
- **BUG-5 (scenarios 37, 38 and 40):** when Reverb is unreachable, `ShouldBroadcastNow` events throw. The send (`POST …/messages`), `/read` and `PATCH /quote/{ref}/cancel` then return 500, although the data was saved.
- **BUG-6 (scenario 10):** after older messages are prepended, the first message moves by about 36 px, roughly the height of the top spinner, which is removed in the same update.

## Observations

- On a thread opened within the first moment, the first typing whisper is dropped if it is sent before the channel subscription succeeds. The 1.5 s throttle then delays the indicator. Scenario 27 waits for channel authorization before typing.
- Screenshots are in `test-results/message/{desktop,mobile,narrow}/`. They include `12-inquiry-cards-*` and `12-schedule-cards-*` at 1280 and 320; the 390 shots come from the pending re-run.
