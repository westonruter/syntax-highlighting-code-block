#!/bin/bash

set -e
cd "$(dirname "$0")/.."

current_highlight_php_version=$(curl -sq  https://api.github.com/repos/scrivo/highlight.php/releases | grep -oE '"tag_name":\s*"[^"]*' | head -n1 | sed 's/.*"v//')
if [[ -z $current_highlight_php_version ]]; then
    echo "Unable to get version"
    exit 1
fi

current_highlight_js_version=$( sed "s/\.[[:digit:]]*$//" <<< "$current_highlight_php_version" )

echo "Current highlight.php version: $current_highlight_php_version"
echo "Current highlight.js version: $current_highlight_js_version"

set -x

composer require "scrivo/highlight.php:v$current_highlight_php_version"

npm install --save-dev "highlightjs/highlight.js#$current_highlight_js_version"

php bin/generate-language-names.php

git add composer.json composer.lock package-lock.json package.json language-names.php

git status

echo "Do: git commit -m 'Update highlight.php to $current_highlight_php_version'"
