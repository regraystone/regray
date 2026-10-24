# Load test results

k6 scripts for the four scenarios, run against a local copy of the production
stack. They are not part of CI. **Never run them against production.** Each
script refuses any host but the local stack unless `STAGING_HOST` names one.

## Agreed thresholds

A run fails when either is crossed:

- 5xx responses over **0.5%** (`server_errors`, every scenario)
- p95 of the scenario's main request over **800 ms**

800 ms is a regression gate for a laptop Docker stack, not a production SLO.

## How to run

The stack is the repo's own `docker-compose.yml` (Caddy, PHP-FPM, Reverb,
worker, scheduler, Postgres 17) under its own project name, with
`load/compose.load.yml` publishing Postgres on `127.0.0.1:54329` so the host
can seed it. The app runs with `APP_ENV=production` and `APP_DEBUG=false`.

1. Make a private env file from `.env.docker.example` (keep it out of the
   repo): `APP_ENV=production`, `APP_DEBUG=false`, a fresh `APP_KEY`
   (`php artisan key:generate --show`), any `REVERB_APP_ID/KEY/SECRET`, a
   `DB_PASSWORD`, and `APP_ENV_FILE=<that file's absolute path>`.
2. Build and start:

    ```sh
    docker compose -p regray-load -f docker-compose.yml -f load/compose.load.yml --env-file <env> up -d --build --wait
    ```

3. Seed from the host (the image has no dev dependencies, which the seeders
   need). Real environment variables beat `.env`, so this only touches the
   load database:

    ```sh
    DB_HOST=127.0.0.1 DB_PORT=54329 DB_DATABASE=regray DB_USERNAME=regray DB_PASSWORD=<pw> \
      php artisan migrate --force && \
    DB_HOST=127.0.0.1 DB_PORT=54329 DB_DATABASE=regray DB_USERNAME=regray DB_PASSWORD=<pw> \
      php artisan db:seed --class=LoadTestSeeder --force
    ```

4. Run each scenario from the k6 image on the stack's network, so requests
   reach Caddy as `http://web` from one client IP:

    ```sh
    docker run --rm --network regray-load_frontend -v "$PWD/load:/load" grafana/k6:2.3.0 run /load/<scenario>.js
    ```

    Run `login-attempts.js` last or a minute apart from the others: it locks
    `client@example.com` out for a minute on purpose.

5. Tear down with `docker compose -p regray-load ... down -v`.

## Results

Run on 2026-10-24 on Docker Desktop (2 CPUs, 4 GB for the whole VM, shared by
k6, Postgres and the app). PHP-FPM runs its image default of 4 workers.
Latencies are k6's `http_req_duration` for the scenario's main request.

- **Before** is Steps 1 to 3: trusted proxies, Pulse, Sentry (no DSN) and the
  N+1 check, with cache and queue on `database`. Only the existing limits
  (login, Message page) are on.
- **After** adds Step 4's limits, still on `database`.
- **After, Redis** is the same code with `CACHE_STORE=redis`,
  `QUEUE_CONNECTION=redis` and the worker running Horizon.
- Step 5 (caching) is deferred until Pulse has a day of production data, so
  there is no "after caching" run yet.

Every run passed both thresholds.

### 1. Booking flood (one client, 2 bookings a second for 60 s)

| Run          | Saved (302) | 429 | 5xx | p95     |
| ------------ | ----------- | --- | --- | ------- |
| Before       | 120         | 0   | 0   | 59.5 ms |
| After        | 11          | 109 | 0   | 41.1 ms |
| After, Redis | 10          | 111 | 0   | 54.2 ms |

Before, one client could book 120 appointments a minute. After, 10 a minute
get through (the eleventh lands as the window rolls over at 60 s). Every saved
booking got its schedule card in the Regray Team thread, with no failed jobs,
on both the database queue and Horizon.

### 2. Message burst (one client, 5 sends a second for 30 s, one thread)

| Run    | Sent | 429 | 5xx | p95     |
| ------ | ---- | --- | --- | ------- |
| Before | 59   | 91  | 0   | 61.1 ms |
| After  | 59   | 91  | 0   | 60.7 ms |

Unchanged, as expected: the `messages` limit (60 a minute) was already on.

### 3. Stone listing with filters (50 clients, one visit every 1 to 2 s each)

| Run                  | Requests | 200 | 429 | 5xx | median  | p90    | p95    | max      |
| -------------------- | -------- | --- | --- | --- | ------- | ------ | ------ | -------- |
| Before               | 4,400    | all | 0   | 0   | 47.7 ms | 123 ms | 192 ms | 883 ms   |
| After                | 4,253    | all | 0   | 0   | 70.2 ms | 224 ms | 334 ms | 776 ms   |
| After, Redis (run 1) | 4,322    | all | 0   | 0   | 49.8 ms | 139 ms | 315 ms | 1,280 ms |
| After, Redis (run 2) | 4,381    | all | 0   | 0   | 51.4 ms | 128 ms | 218 ms | 642 ms   |

- No client reached `heavy-reads` (120 a minute), so the limit costs real
  users nothing.
- On `database`, each request's limit check is extra reads and writes on
  Postgres, which shows in the median and p90. On Redis the median and p90 are
  back to the baseline. **Move the cache to Redis before or with the limits.**
- p95 moves by about 100 ms between identical runs on this VM, so treat one
  run's p95 as noisy. Most of the tail is 50 users queueing for 4 FPM workers.

### 4. Login attempts (ten wrong passwords, one account, one IP)

| Run    | 422 (invalid)   | 429              | 5xx | p95      |
| ------ | --------------- | ---------------- | --- | -------- |
| Before | attempts 1 to 5 | attempts 6 to 10 | 0   | 228.7 ms |
| After  | attempts 1 to 5 | attempts 6 to 10 | 0   | 229.4 ms |

The p95 is bcrypt hashing, by design.

### What Pulse recorded during the runs

- `POST /login` was the only slow request (up to 853 ms while 50 clients
  signed in at once).
- The slowest query was the stone search's
  `count(*) ... name::text ILIKE ?` at 108 ms. A trigram index (`pg_trgm`)
  would help it more than a cache, because every search term is a different
  key.
