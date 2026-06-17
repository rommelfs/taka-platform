#!/usr/bin/env bash

set -e

cd "$(git rev-parse --show-toplevel)"

echo "=== PHP syntax ==="
find . -name "*.php" -print0 | xargs -0 -n1 php -l

echo
echo "=== Merge markers ==="
grep -R "<<<<<<<\|=======\|>>>>>>>" -n . \
    --exclude-dir=.git \
    || true

echo
echo "Lint OK."
