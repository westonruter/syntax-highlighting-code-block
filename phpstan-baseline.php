<?php declare(strict_types = 1);

$ignoreErrors = [];
   $ignoreErrors[] = [
	'message' => '#^Access to an undefined property WP_Block_Type\\:\\:\\$editor_script\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/syntax-highlighting-code-block.php',
];
   $ignoreErrors[] = [
	'message' => '#^Access to an undefined property WP_Block_Type\\:\\:\\$editor_style\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/syntax-highlighting-code-block.php',
];

return ['parameters' => ['ignoreErrors' => $ignoreErrors]];