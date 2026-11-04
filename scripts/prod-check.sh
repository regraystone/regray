#!/usr/bin/env bash
#
# Checks production end to end. Run it on the server:
#
#   cd /srv/regray && bash prod-check.sh
#   bash prod-check.sh --mail=you@example.com   # also send a test email
#
# First the containers (all up, none restarting, running the image the last
# deploy pinned in .env), then `php artisan app:health-check` inside the app
# container for the database, cache, queue, scheduler, mail, Reverb and the
# public site. Exits 1 if anything failed. The deploy copies this file next to
# docker-compose.yml.

set -uo pipefail
cd "$(dirname "$0")"

failed=0
ok() { printf '  \033[32mOK\033[0m    %s\n' "$1"; }
fail() { printf '  \033[31mFAIL\033[0m  %s\n' "$1"; failed=1; }

echo "Containers"

for service in $(docker compose config --services); do
    state=$(docker compose ps -a --format '{{.State}} {{.Health}}' "$service" | head -n 1)

    case "$state" in
        "running unhealthy"* | "running starting"*) fail "$service is $state" ;;
        running*) ok "$service is $state" ;;
        "") fail "$service has no container" ;;
        *) fail "$service is $state (see: docker compose logs --tail 100 $service)" ;;
    esac
done

echo
echo "Images"

for pair in app:APP_IMAGE web:WEB_IMAGE; do
    service=${pair%%:*}
    expected=$(grep -E "^${pair#*:}=" .env | cut -d= -f2-)
    running=$(docker inspect --format '{{.Config.Image}}' "$(docker compose ps -q "$service")" 2>/dev/null)

    if [[ -n "$expected" && "$running" == "$expected" ]]; then
        ok "$service runs ${running##*:}"
    else
        fail "$service runs '${running:-nothing}', but .env pins '$expected': the deploy did not replace it"
    fi
done

echo
docker compose exec -T app php artisan app:health-check "$@" || failed=1

exit "$failed"
