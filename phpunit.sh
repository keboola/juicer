#!/usr/bin/env bash
set -e

composer selfupdate
composer install --no-interaction

./vendor/bin/phpcs --standard=psr2 --ignore=vendor -n .
# Intentionally `|| true` until errors are fixed
./vendor/bin/phpstan analyse --level=4 Client Common Config Exception Extractor Filesystem Pagination Parser Tests || true
./vendor/bin/phpunit "$@"