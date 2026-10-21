# Inquiry flow: E2E report

## Environment

- Branch `feat/messaging`. The suite came in 1f179de; the fixes for the findings below came in the commit after it. Windows 11, local Chrome (`E2E_CHROME_PATH`); `playwright install` was not run.
- The global setup (`tests/e2e/support/global-setup.ts`) refuses to run unless `APP_ENV` is local or testing, refuses a cached config and the dev database `regray-studio`. It then:
    - resets the dedicated database `regray-studio-e2e` (`migrate:fresh --seed`, then `E2EMessageSeeder` and `E2EInquirySeeder`);
    - starts its own `php artisan serve` (:8001), Reverb (:8081) and a queue worker (`queue:work --name=e2e`), so the queue worker **was running**;
    - signs each role in once and saves its cookies under `tests/e2e/.auth/`.
- Vite: the running `npm run dev` (`public/hot`, `http://[::1]:5173`).
- Accounts (passwords from `E2E_PASSWORD_*`, default UserFactory's):
    - From `E2EInquirySeeder`: `e2e.inquiry.a@` (client A), `e2e.inquiry.b@` (client B), `e2e.inquiry.unverified@`. Plus an inactive service, `e2e-retired-service`.
    - From `DemoAccountsSeeder`: `marketing@`, `sales@`.

## Results

After the fixes: **39 of 39 pass.**

- Desktop: one run of the whole suite, against a freshly seeded database. Scenario 7 timed out in that run while other work loaded the machine, and passed when rerun alone.
- 390 px and 320 px: scenarios 6, 7, 34 and 35, all pass.

| #     | Status | Note                                                                                            |
| ----- | ------ | ----------------------------------------------------------------------------------------------- |
| 1–5   | pass   | 3: 403 with the forbidden page on both URLs                                                     |
| 6     | pass   | All three widths; screenshots of every step                                                     |
| 7     | pass   | All three widths; screenshots of every step                                                     |
| 8     | pass   | DB check in the spec                                                                            |
| 9–10  | pass   |                                                                                                 |
| 11    | pass   | Was a fail: Continue was never disabled. Fixed (F-1)                                            |
| 12    | pass   |                                                                                                 |
| 13    | pass   | Was "not built": no "advise me" finish. Fixed (F-2)                                             |
| 14    | pass   | "Parañaque" read back from the database                                                         |
| 15    | pass   | Was a fail: BUG-I1. Fixed                                                                       |
| 16–21 | pass   |                                                                                                 |
| 22    | pass   | No request at all while the files were refused                                                  |
| 23    | pass   | Stored on disk `local` (`storage/app/private`); four guessed `/storage/...` URLs answer 403/404 |
| 24    | pass   | Ten files reach the database                                                                    |
| 25    | pass   | A stored step of 5 is pulled back to 3                                                          |
| 26    | pass   | "Start new" in the Quote page's draft prompt                                                    |
| 27–30 | pass   |                                                                                                 |
| 31    | pass   | Was a fail: BUG-I2 and the message. Fixed                                                       |
| 32    | pass   | One POST, one row                                                                               |
| 33–36 | pass   | 34 at 1280, 390 and 320                                                                         |
| 37    | pass   | Was a fail: BUG-I3 and BUG-I4. Fixed                                                            |
| 38    | pass   | Every spec also fails on console errors outside the (empty) allow-list                          |
| 39    | pass   | Only `127.0.0.1:8001` and `[::1]:5173`; checked in every spec                                   |

## Added later

| #   | Status | Note                                                                                                                                                                                                         |
| --- | ------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| 40  | pass   | A sent inquiry posts a "New Inquiry" card into the client's Regray Team thread. The client sees it, and so does marketing (the support pool). `k-regray-team-card.spec.ts`                                   |
| 41  | pass   | Without a group chat, cancelling posts an "Inquiry cancelled" card into the same Regray Team thread, after the "New Inquiry" one. With a group chat, the card goes to the group instead: Message scenario 40 |

## Findings and their fixes

- **BUG-I1 (15):** the notes counter stayed at "0 / 1000" while typing.
    - Cause: `inquiry-project-details.tsx` read `watch('notes')` from the form context, which does not redraw that component.
    - Fix: `useWatch`.
- **BUG-I2 (31):** a 500 on send opened Inertia's own error dialog (the raw response in an iframe) over the page's error panel, so "Try again" could not be pressed until it was closed.
    - Fix: the send passes `onHttpException: () => false`, so the page's panel is the only answer.
    - The panel's fallback sentence is now the server's own, "We could not save your inquiry. Your answers are still here, so you can try again."
- **BUG-I3 (37):** axe `label-content-name-mismatch` (serious) on every page: the header's account button was named "Account" but showed the initials.
    - Fix: named "Account, {initials}" in `layouts/workspace-layout.tsx`.
    - axe ignores text in parentheses, so "Account (IA)" did not pass.
- **BUG-I4 (37):** axe `label` (critical): the hidden file input had no name.
    - Fix: `aria-label="Attach files"` and `tabIndex={-1}`. The button beside it is the keyboard way in.
- **F-1 (11):** Continue on Services is disabled until a service is picked, as the scenario asks. The page used to leave it enabled on purpose and object on press.
- **F-2 (13):** `StoneSeeder` writes a "Not sure yet, advise me" finish (`is_advisory`). Stone profiles already leave advisory finishes out. A visit's extra stones now take it instead of the first real finish.

## Could not test

- Docker Compose was not used; the stack is `php artisan serve` with the running Vite dev server.
