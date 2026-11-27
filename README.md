# Regray

The Regray Stone platform: a public Inquire flow for stone and custom furniture
projects, and an admin dashboard for brands, catalogue, quotations, inquiries,
scheduling and an inbox.

- **Backend:** Laravel 13 (PHP 8.3+), Postgres 17.
- **Frontend:** Inertia + React + TypeScript, built with Vite.
- **Realtime:** Laravel Reverb (WebSockets) for the inbox.
- **Queues:** database by default; Redis/Valkey + Horizon in production.
- **Also:** Fortify (auth + 2FA), Socialite (Google), Spatie Permission,
  Pulse, Sentry.

Everything runs in Docker; PHP is not expected to run on the host.

## Local development

The stack runs under Docker Compose, reading `.env.docker`.

```sh
# 1. Create your local env file (git-ignored) and edit secrets as needed.
cp .env.docker.example .env.docker

# 2. Build the images.
docker compose --env-file .env.docker build

# 3. Generate an app key and paste the base64:... line into .env.docker (APP_KEY=).
docker compose --env-file .env.docker run --rm --entrypoint php app artisan key:generate --show

# 4. Start the stack.
docker compose --env-file .env.docker up -d

# 5. Migrate and seed the database (the entrypoint does not do this).
docker compose --env-file .env.docker exec app php artisan migrate --seed
```

The app is then at <http://localhost:8080>. Create an admin to sign in:

```sh
docker compose --env-file .env.docker exec app php artisan app:create-admin
```

> **After pulling new code, rebuild the images.** The app code is baked into the
> image, so a plain `up` keeps running the old code:
> `docker compose --env-file .env.docker up -d --build`.

Run any artisan command the same way:

```sh
docker compose --env-file .env.docker exec app php artisan <command>
```

To reach Postgres from a GUI on your Mac, `docker-compose.local.yml` publishes
it on `127.0.0.1:5432`.

## Tests and checks

`composer ci:check` is the gate CI runs — frontend lint and type-check, Pint,
PHPStan, and the core tests: the `Core` PHP suite in `phpunit.xml` (sign-in,
permissions, inquiries, quotes, schedules, messaging access, the schema, ops
commands) and the JS tests in `npm run test:core`. `composer ci:full` runs the
same checks with every test, which is worth doing before a large change:

```sh
docker compose --env-file .env.docker exec app composer ci:check
docker compose --env-file .env.docker exec app composer ci:full
```

Individual pieces:

```sh
docker compose --env-file .env.docker exec app php artisan test     # PHP (Pest)
docker compose --env-file .env.docker exec app ./vendor/bin/pint    # format PHP
docker compose --env-file .env.docker exec app ./vendor/bin/phpstan analyse
npm run check          # lint/format the frontend
npm run types:check    # tsc
npm test               # JS unit tests
```

### Browser end-to-end tests

Playwright drives the inquiry and inbox flows. These run locally only, not in
CI. The suite's setup creates and seeds its own `regray-e2e` database and
starts the app, Reverb and a queue worker against it, so it must run with
`APP_ENV=local` (or `testing`) and an uncached config.

```sh
php artisan config:clear
npm run e2e
```

See `tests/e2e/inquiry/REPORT.md` and `tests/e2e/message/REPORT.md`.

## Dependency audits

CI fails when a dependency has a known advisory (see `.github/workflows/tests.yml`,
the `audit` job). To check locally:

```sh
composer audit                 # PHP
npm audit --omit=dev           # JS, production dependencies only
```

Dependabot also opens weekly update PRs for npm, Composer, Docker and Actions.

## Deployment

Pushing to `main` runs `.github/workflows/deploy.yml`: it calls the test
workflow, builds the `app` and `web` images, pushes them to GHCR, then SSHes to
the server, pulls the images, runs `php artisan migrate --force` and swaps the
containers, and finally checks the public `/up` endpoint.

To roll back, run the Deploy workflow by hand (`workflow_dispatch`) with
`image_sha` set to the full commit SHA whose images you want. That skips the
build and re-points the server at those images.

Production health can be checked on the server:

```sh
cd /srv/regray && bash prod-check.sh
# or, inside the app container:
php artisan app:health-check
```

The health check fails in production if `APP_DEBUG` is on, `APP_URL` is not
HTTPS, `SESSION_SECURE_COOKIE` is not `true`, or Sentry has no DSN.

## Storage (AWS S3)

In production, images (`FILESYSTEM_PUBLIC_DISK=s3-public`) and private files
(`FILESYSTEM_PRIVATE_DISK=s3-private`) live in two S3 buckets. See
`.env.example` for the `AWS_*` variables.

The catalogue images the seeders reference are committed under
`storage/app/public/` and are **not** baked into the images, so a freshly seeded
production database needs the same files in the public bucket. Upload them with:

```sh
AWS_PUBLIC_BUCKET=<bucket> bash scripts/upload-catalog-images.sh --dry-run
AWS_PUBLIC_BUCKET=<bucket> bash scripts/upload-catalog-images.sh
```

It never overwrites or deletes existing objects. For ad-hoc management, use the
AWS CLI with a named profile (`aws configure --profile regray`), e.g.
`aws s3 ls s3://<bucket>/ --profile regray`.

## Backups

`app:backup-database` runs nightly (00:00 Manila), streaming a `pg_dump` to the
private disk under `backups/`. Dumps older than `BACKUP_RETENTION_DAYS` (default
30, `0` keeps all) are pruned after each successful backup.

> The app's S3 key can delete from the backups prefix, so the pruning only caps
> growth. Protect the history with an S3 lifecycle rule and Object Lock (or
> versioning) on the `backups/` prefix, and test a restore periodically.
