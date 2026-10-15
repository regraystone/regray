#!/bin/sh
set -e

php artisan optimize --no-interaction

exec "$@"
