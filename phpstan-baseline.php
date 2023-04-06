<?php declare( strict_types=1 );

$ignoreErrors   = [];
$ignoreErrors[] = [
	'message' => '#^Access to an undefined property WP_Block_Type\\:\\:\\$editor_script\\.$#',
	'count'   => 1,
	'path'    => __DIR__ . '/syntax-highlighting-code-block.php',
];
$ignoreErrors[] = [
	'message' => '#^Access to an undefined property WP_Block_Type\\:\\:\\$editor_style\\.$#',
	'count'   => 1,
	'path'    => __DIR__ . '/syntax-highlighting-code-block.php',
];

// See <https://github.com/phpstan/phpstan-src/pull/2277#issuecomment-1481954014>.
$ignoreErrors[] = [
	'message' => '#^Call to function property_exists\\(\\) with WP_Block_Type and \'editor_script…\' will always evaluate to true\\.$#',
	'count'   => 1,
	'path'    => __DIR__ . '/syntax-highlighting-code-block.php',
];
$ignoreErrors[] = [
	'message' => '#^Call to function property_exists\\(\\) with WP_Block_Type and \'editor_style_handles\' will always evaluate to true\\.$#',
	'count'   => 1,
	'path'    => __DIR__ . '/syntax-highlighting-code-block.php',
];

// TODO: Figure out why this is being raised. It can't seem to be due to <https://github.com/phpstan/phpstan/issues/3770> since it's not a closure.
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$autoload_function of function spl_autoload_register expects callable\\(string\\)\\: void, \'Highlight…\' given\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/syntax-highlighting-code-block.php',
];

return [ 'parameters' => [ 'ignoreErrors' => $ignoreErrors ] ];
