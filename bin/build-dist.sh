#!/bin/bash

set -e
cd $(dirname $0)/..

if [ ! -e dist ]; then
	mkdir dist
fi

echo "Exporting repo to dist directory"
git archive --format=tar HEAD | (cd dist/ && tar xf -)

cd dist

# Symlink node_modules rather than installing anew if possible.
if [ -e ../node_modules ]; then
	ln -s ../node_modules node_modules
else
	npm install
fi

# Install composer dependencies with optimized autoloader and excluding dev-dependencies.
composer install --no-dev --classmap-authoritative --optimize-autoloader

# Since the "highlight.php" directory name can trip up some systems, rename to "highlight-php".
mv vendor/scrivo/highlight{.php,-php}
find vendor/autoload.php vendor/composer -type f -print0 | xargs -0 sed -i "s:/highlight\.php/:/highlight-php/:g"
sed -i "s/const DEVELOPMENT_MODE = true;.*/const DEVELOPMENT_MODE = false;/g" syntax-highlighting-code-block.php

# Build the JS.
npm run build:js

# Convert markdown README.
npm run build:transform-readme

# Grab amend the version with the commit hash.
VERSION=$(grep 'PLUGIN_VERSION' syntax-highlighting-code-block.php | cut -d\' -f2)
if [[ $VERSION == *-* ]]; then
	NEW_VERSION="$VERSION-$(date -u +%Y%m%dT%H%M%SZ)-$(git --no-pager log -1 --format=%h --date=short)"
	VERSION_ESCAPED="${VERSION//./\\.}"
	sed -i "s/$VERSION_ESCAPED/$NEW_VERSION/g" syntax-highlighting-code-block.php
	echo "Detected non-stable version: $VERSION"
	echo "Creating build for version: $NEW_VERSION"
fi
