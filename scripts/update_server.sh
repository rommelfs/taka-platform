#!/usr/bin/env bash

set -e

SERVER="kan"
PLUGIN="/var/www/html/wp-content/plugins/taka-tour-website-builder"

ssh "$SERVER" <<EOF
cd "$PLUGIN"

git switch main
git fetch origin
git pull --ff-only

find . -name "*.php" -print0 | xargs -0 -n1 php -l

grep -R "<<<<<<<\\|=======\\|>>>>>>>" -n . --exclude-dir=.git || true

echo
echo "Server updated."
EOF
