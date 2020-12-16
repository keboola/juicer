#!/usr/bin/env bash
set -e

php --version

echo "Starting tests" >&1
./vendor/bin/phpcs -n --ignore=vendor,.tmp --extensions=php .
./vendor/bin/phpstan analyse --level=max --no-progress -c phpstan.neon Client Config Exception Pagination Parser Tests

./vendor/bin/phpunit --coverage-clover build/logs/clover.xml
curl -L https://codeclimate.com/downloads/test-reporter/test-reporter-latest-linux-amd64 > ./cc-test-reporter
chmod +x ./cc-test-reporter
./cc-test-reporter after-build

echo "Tests Finished" >&1
