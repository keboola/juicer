#!/usr/bin/env bash
set -e

composer selfupdate
composer install --no-interaction

./vendor/bin/phpcs --standard=psr2 --ignore=vendor -n .
# ./vendor/bin/phpstan analyse . --level=4 || true
./vendor/bin/phpunit "$@"