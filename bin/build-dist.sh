#!/bin/bash

set -e
cd $(dirname $0)/..

if [ -e dist ]; then
	echo "Cleaning dist directory"
	rm -r dist
fi
mkdir dist

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
composer install --no-dev -ao

# Since the "highlight.php" directory name can trip up some systems, rename to "highlight-php".
mv vendor/scrivo/highlight{.php,-php}
find vendor/autoload.php vendor/composer -type f -print0 | xargs -0 sed -i.bak "s:/highlight\.php/:/highlight-php/:g"
rm vendor/autoload.php.bak $(find vendor/composer -type f -name '*.bak')

# Build the JS.
npm run build:js

# Convert markdown README.
npm run build:transform-readme

# TODO: commitHash=$(git --no-pager log -1 --format=%h --date=short)
# TODO: const versionAppend = new Date().toISOString().replace(/\.\d+/, '').replace(/-|:/g, '') + '-' + commitHash;
# Append version inside of syntax-highlighting-code-block.php
# TODO: Should the version just be n.e.x.t?


# Remove files that we don't need anymore.
rm -r \
	.editorconfig \
	.eslintignore \
	.eslintrc.js \
	.github \
	.gitignore \
	.husky \
	.lintstagedrc.js \
	.nvmrc \
	.phpcs.xml.dist \
	.prettierrc \
	.wordpress-org \
	.wp-env.json \
	Gruntfile.js \
	README.md \
	bin \
	block-library.md5 \
	composer.json \
	composer.lock \
	node_modules \
	package-lock.json \
	package.json \
	phpstan.neon.dist \
	src \
	tests

# Creating ZIP of build.
if [ -e ../syntax-highlighting-code-block.zip ]; then
	rm ../syntax-highlighting-code-block.zip
fi
zip -r ../syntax-highlighting-code-block.zip .
cd ..
echo "ZIP of build: $(pwd)/syntax-highlighting-code-block.zip"
