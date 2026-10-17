#!/usr/bin/env bash
# Builds the wordpress.org release zip: build/live-scores-league-tables.zip
# (plugin files only; .distignore lists what stays out). No CI needed.
set -euo pipefail
cd "$(dirname "$0")/.."
slug=live-scores-league-tables
rm -rf build && mkdir -p "build/$slug"
rsync -a --exclude-from=.distignore ./ "build/$slug/"
(cd build && zip -qr "$slug.zip" "$slug")
echo "build/$slug.zip ($(grep -m1 'Stable tag' readme.txt))"
