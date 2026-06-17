#!/bin/bash

find . -name "*.php" -print0 | xargs -0 -n1 php -l
grep -R "<<<<<<<\|=======\|>>>>>>>" -n . --exclude-dir=.git
