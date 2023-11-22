#!/bin/bash

install_path=$( npm run wp-env install-path 2>/dev/null | tail -n1)
if [[ -z "$install_path" ]]; then
	echo "Error: Unable to get install-path. Make sure you first did npm run wp-env start."
	exit 1
fi

mkdir -p vendor/wp-env

core_dir="vendor/wp-env/wp-core"
if [[ -e "$core_dir" ]]; then
	rm "$core_dir"
fi
ln -s "$install_path/WordPress" "$core_dir"
echo "Created $core_dir symlink"

tests_dir="vendor/wp-env/wp-tests-phpunit"
if [[ -e "$tests_dir" ]]; then
	rm "$tests_dir"
fi
ln -s "$install_path/WordPress-PHPUnit/tests/phpunit" "$tests_dir"
echo "Created $tests_dir symlink"
