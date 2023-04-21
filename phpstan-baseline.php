<?php declare( strict_types=1 );

$ignoreErrors   = [];
$ignoreErrors[] = [
	'message' => '#^Access to an undefined property WP_Block_Type\\:\\:\\$editor_script\\.$#',
	'count'   => 1,
	'path'    => '*', // Originally `__DIR__ . '/syntax-highlighting-code-block.php'`, but see https://youtrack.jetbrains.com/issue/WI-63891/PHPStan-ignoreErrors-configuration-isnt-working-with-inspections#focus=Comments-27-6188783.0-0
];
$ignoreErrors[] = [
	'message' => '#^Access to an undefined property WP_Block_Type\\:\\:\\$editor_style\\.$#',
	'count'   => 1,
	'path'    => '*',
];

// See <https://github.com/phpstan/phpstan-src/pull/2277#issuecomment-1481954014>.
$ignoreErrors[] = [
	'message' => '#^Call to function property_exists\\(\\) with WP_Block_Type and \'editor_script…\' will always evaluate to true\\.$#',
	'count'   => 1,
	'path'    => '*',
];
$ignoreErrors[] = [
	'message' => '#^Call to function property_exists\\(\\) with WP_Block_Type and \'editor_style_handles\' will always evaluate to true\\.$#',
	'count'   => 1,
	'path'    => '*',
];

return [ 'parameters' => [ 'ignoreErrors' => $ignoreErrors ] ];
