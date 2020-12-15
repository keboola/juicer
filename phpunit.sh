#!/usr/bin/env bash
set -e

php --version

echo "Starting tests" >&1
./vendor/bin/phpcs --standard=psr2 --ignore=vendor,.tmp -n .
./vendor/bin/phpstan analyse --level=4 Client Config Exception Pagination Parser Tests

./vendor/bin/phpunit --coverage-clover build/logs/clover.xml
curl -L https://codeclimate.com/downloads/test-reporter/test-reporter-latest-linux-amd64 > ./cc-test-reporter
chmod +x ./cc-test-reporter
./cc-test-reporter after-build

echo "Tests Finished" >&1
