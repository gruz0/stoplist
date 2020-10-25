#!/bin/bash
set -euo pipefail

rm -rf rulesets

git clone -b master https://github.com/WordPress-Coding-Standards/WordPress-Coding-Standards.git rulesets/wpcs

composer install -o --no-progress

cp bin/pre-commit .git/hooks/pre-commit
