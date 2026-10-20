# Inquiry flow: E2E report

## Environment

- Branch `feat/messaging`, on top of commit 43bd774. Windows 11, local Chrome (`E2E_CHROME_PATH`); `playwright install` was not run.
- The global setup (`tests/e2e/support/global-setup.ts`) refuses to run unless `APP_ENV` is local or testing, refuses a cached config and the dev database `regray-studio`. It then:
    - resets the dedicated database `regray-studio-e2e` (`migrate:fresh --seed`, then `E2EMessageSeeder` and `E2EInquirySeeder`);
    - starts its own `php artisan serve` (:8001), Reverb (:8081) and a queue worker (`queue:work --name=e2e`), so the queue worker **was running**;
    - signs each role in once and saves its cookies under `tests/e2e/.auth/`.
- Vite: the running `npm run dev` (`public/hot`, `http://[::1]:5173`).
- Accounts (passwords from `E2E_PASSWORD_*`, default UserFactory's):
    - From `E2EInquirySeeder`: `e2e.inquiry.a@` (client A), `e2e.inquiry.b@` (client B), `e2e.inquiry.unverified@`. Plus an inactive service, `e2e-retired-service`.
    - From `DemoAccountsSeeder`: `marketing@`, `sales@`.
- Run section by section (memory on this machine is tight), each against a freshly seeded database.

## Results

| #   | Status        | Note                                                                                                                                       |
| --- | ------------- | ------------------------------------------------------------------------------------------------------------------------------------------ |
| 1   | pass          |                                                                                                                                            |
| 2   | pass          |                                                                                                                                            |
| 3   | pass          | 403 with the forbidden page on both URLs                                                                                                   |
| 4   | pass          |                                                                                                                                            |
| 5   | pass          |                                                                                                                                            |
| 6   | pass          | All three widths; screenshots of every step                                                                                                |
| 7   | pass          | All three widths; screenshots of every step                                                                                                |
| 8   | pass          | DB check in the spec                                                                                                                       |
| 9   | pass          |                                                                                                                                            |
| 10  | pass          |                                                                                                                                            |
| 11  | **fail**      | Spec mismatch: Continue is never disabled; with nothing picked it says "Select at least one service." in the page style (that part passes) |
| 12  | pass          |                                                                                                                                            |
| 13  | **not built** | Finishes have no "advise me" option (edges do). Required edge/finish, edge "advise me" and the 20-row cap all pass                         |
| 14  | pass          | "Parañaque" read back from the database                                                                                                    |
| 15  | **fail**      | BUG-I1. The limits hold (150 / 1000, server 422 on 151 / 1001); the counter does not                                                       |
| 16  | pass          |                                                                                                                                            |
| 17  | pass          |                                                                                                                                            |
| 18  | pass          |                                                                                                                                            |
| 19  | pass          |                                                                                                                                            |
| 20  | pass          |                                                                                                                                            |
| 21  | pass          |                                                                                                                                            |
| 22  | pass          | No request at all while the files were refused                                                                                             |
| 23  | pass          | Stored on disk `local` (`storage/app/private`); four guessed `/storage/...` URLs answer 403/404                                            |
| 24  | pass          | Ten files reach the database                                                                                                               |
| 25  | pass          | A stored step of 5 is pulled back to 3                                                                                                     |
| 26  | pass          | "Start new" in the Quote page's draft prompt                                                                                               |
| 27  | pass          |                                                                                                                                            |
| 28  | pass          |                                                                                                                                            |
| 29  | pass          |                                                                                                                                            |
| 30  | pass          |                                                                                                                                            |
| 31  | **fail**      | BUG-I2, plus a message mismatch. Answers kept; retry saves exactly one                                                                     |
| 32  | pass          | One POST, one row                                                                                                                          |
| 33  | pass          |                                                                                                                                            |
| 34  | pass          | 1280, 390 and 320                                                                                                                          |
| 35  | pass          |                                                                                                                                            |
| 36  | pass          |                                                                                                                                            |
| 37  | **fail**      | BUG-I3 and BUG-I4                                                                                                                          |
| 38  | pass          | Every other spec also fails on console errors outside the (empty) allow-list                                                               |
| 39  | pass          | Only `127.0.0.1:8001` and `[::1]:5173`; checked in every spec                                                                              |

## Bugs (not fixed in this commit)

- **BUG-I1 (15):** the notes counter stays at "0 / 1000" while typing.
    - Steps: Requirements step, type "Notes" into Additional notes.
    - Expected "5 / 1000"; actual "0 / 1000". Same at 1000 characters.
    - Cause: `inquiry-project-details.tsx` reads `watch('notes')` from the form context, which does not redraw this component.
    - Screenshot: `test-results/artifacts/inquiry-c-step-rules-15-*/test-failed-1.png`.
- **BUG-I2 (31):** when the send answers 500, Inertia's own error dialog (the raw response in an iframe) opens over the page and covers the page's error panel; "Try again" cannot be pressed until it is closed.
    - Steps: build a visit, make `POST /quote/inquire` answer 500, press Request a Quote.
    - Also: the panel says "Something went wrong on our side. Your answers are still saved…", not the scenario's "We could not save your inquiry. Your answers are still here…". That sentence is the controller's flash for a failure inside the save, which a raw 500 never reaches.
- **BUG-I3 (37):** axe `label-content-name-mismatch` (serious) on every step: the header's account button is named "Account" but shows the initials. `layouts/workspace-layout.tsx`.
- **BUG-I4 (37):** axe `label` (critical) on the Requirements step: the hidden file input has no name. `components/file-upload.tsx`.

## Could not test

- Docker Compose was not used; the stack is `php artisan serve` with the running Vite dev server.
