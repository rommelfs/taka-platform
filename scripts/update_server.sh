#!/bin/bash

ssh kan '
cd /var/www/html/wp-content/plugins/taka-tour-website-builder &&
git switch main &&
git fetch origin &&
git pull --ff-only &&
find . -name "*.php" -print0 | xargs -0 -n1 php -l
'
