#!/usr/bin/env php
<?php
/**
 * Rewrite README.md into WordPress's readme.txt
 *
 * @codeCoverageIgnore
 * @package WestonRuter\TransformReadme
 */

// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
// phpcs:disable WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

namespace WestonRuter\TransformReadme;

/**
 * Logs a message.
 *
 * @param string $message Message.
 */
function log( string $message ): void {
	fwrite( STDERR, "$message\n" );
}

/**
 * Exits with an error.
 *
 * @param string $message     Message.
 * @param int    $line_number Line number.
 *
 * @phpstan-return never
 */
function error( string $message, int $line_number ): void {
	fwrite( STDERR, "$message (from line $line_number)\n" );
	exit( 1 );
}

/**
 * Logs a warning.
 *
 * @param string $message     Message.
 * @param int    $line_number Line number.
 */
function warn( string $message, int $line_number ): void {
	fwrite( STDERR, "$message (from line $line_number)\n" );
}

if ( 'cli' !== php_sapi_name() ) {
	error( 'Must run from CLI', __LINE__ );
}

$readme_md = file_get_contents( __DIR__ . '/../README.md' );
if ( ! is_string( $readme_md ) ) {
	error( 'Unable to read from README.md.', __LINE__ );
}

$readme_txt = $readme_md;

// Transform code blocks.
$readme_txt = (string) preg_replace_callback(
	'/(^|\n)```(\w+)?\n(.+?\n)```/s',
	static function ( $matches ): string {
		$text_content = trim( $matches[3] );
		$text_content = htmlspecialchars( $text_content );
		return $matches[1] . '<pre>' . $text_content . '</pre>';
	},
	$readme_txt
);

// Transform the sections above the description.
$readme_txt = (string) preg_replace_callback(
	'/^.+?(?=## Description)/s',
	static function ( $matches ) {
		// Delete lines with images, linked images (badges), or comments.
		$input = trim( (string) preg_replace( '/^(\[?!\[[^]]+?]\([^)]+?\)(](.+?))?|<img[^>]+?>|<!--.+?-->)$/m', '', $matches[0] ) );

		$parts = preg_split( '/\n\n+/', $input );

		if ( ! is_array( $parts ) || 3 !== count( $parts ) ) {
			error( 'Too many sections in header found.', __LINE__ );
		}

		$header = $parts[0];

		$description = $parts[1];
		if ( strlen( $description ) > 150 ) {
			error( "The short description is too long: $description", __LINE__ );
		}

		$metadata = array();
		foreach ( explode( "\n", $parts[2] ) as $meta ) {
			$meta = trim( $meta );
			if ( ! preg_match( '/^\*\*(?P<key>.+?):\*\*\s+(?P<value>.+)/', $meta, $matches ) ) {
				error( "Parse error for meta line: $meta.", __LINE__ );
			}

			$unlinked_value = preg_replace( '/\[(.+?)]\(.+?\)/', '$1', $matches['value'] );

			$metadata[ $matches['key'] ] = $unlinked_value;

			// Extract License URI from the link.
			if ( 'License' === $matches['key'] ) {
				$license_uri = (string) preg_replace( '/\[.+?]\((.+?)\)/', '$1', $matches['value'] );

				if ( 0 !== strpos( $license_uri, 'http' ) ) {
					error( "Unable to extract License URI from: $meta.", __LINE__ );
				}

				$metadata['License URI'] = $license_uri;
			}
		}

		$expected_metadata = array(
			'Contributors',
			'Tags',
			'Tested up to',
			'Stable tag',
			'License',
			'License URI',
		);
		foreach ( $expected_metadata as $key ) {
			if ( empty( $metadata[ $key ] ) ) {
				error( "Failed to parse metadata. Missing: $key", __LINE__ );
			}
		}

		$replaced = "$header\n\n";
		foreach ( $metadata as $key => $value ) {
			$replaced .= "$key: $value\n";
		}
		$replaced .= "\n$description\n\n";

		return $replaced;
	},
	$readme_txt
);

// Replace image-linked YouTube videos with bare URLs.
$readme_txt = (string) preg_replace(
	'#\[!\[.+?]\(.+?\)]\((https://(?:(?:www\.)?youtube\.com|youtu\.be)/.+?)\)#',
	'$1',
	$readme_txt
);

// Fix up the screenshots.
$screenshots_captioned = 0;
$readme_txt            = (string) preg_replace_callback(
	'/(?P<heading>\n## Screenshots(?: ##)?\n+)(?P<body>.+?\n)(?=## Changelog)/s',
	static function ( $matches ) use ( &$screenshots_captioned ) {
		if ( ! preg_match_all( '/^### (.+?)(?: ###)?$/m', $matches['body'], $screenshot_matches ) ) {
			error( 'Unable to parse screenshot headings.', __LINE__ );
		}

		$screenshot_txt = $matches['heading'];
		foreach ( $screenshot_matches[1] as $i => $screenshot_caption ) {
			$screenshot_txt .= sprintf( "%d. %s\n", $i + 1, $screenshot_caption );
			$screenshots_captioned++;
		}
		$screenshot_txt .= "\n";

		return $screenshot_txt;
	},
	$readme_txt,
	1,
	$replace_count
);
if ( 0 === $replace_count ) {
	warn( 'There are no screenshots.', __LINE__ );
}

$screenshot_files = glob( __DIR__ . '/../.wordpress-org/screenshot-*' );
if ( ! is_array( $screenshot_files ) || count( $screenshot_files ) !== $screenshots_captioned ) {
	error( 'Number of screenshot files does not match number of screenshot captions.', __LINE__ );
}
foreach ( $screenshot_files as $i => $screenshot_file ) {
	if ( 0 !== strpos( basename( $screenshot_file ), sprintf( 'screenshot-%d.', $i + 1 ) ) ) {
		error( "Screenshot filename is not sequential: $screenshot_file.", __LINE__ );
	}
}

// Convert Markdown headings into WP readme headings for good measure.
$readme_txt = (string) preg_replace_callback(
	'/^(#+)\s(.+?)(\s\1)?$/m',
	static function ( $matches ) {
		$md_heading_level = strlen( $matches[1] );
		$heading_text     = $matches[2];

		// #: ===
		// ##: ==
		// ###: =
		$txt_heading_level = 4 - $md_heading_level;
		if ( $txt_heading_level <= 0 ) {
			error( "Heading too small to transform: $matches[0].", __LINE__ );
		}

		return sprintf(
			'%1$s %2$s %1$s',
			str_repeat( '=', $txt_heading_level ),
			$heading_text
		);
	},
	$readme_txt,
	-1,
	$replace_count
);
if ( 0 === $replace_count ) {
	error( 'Unable to transform headings.', __LINE__ );
}

if ( ! file_put_contents( __DIR__ . '/../readme.txt', $readme_txt ) ) {
	error( 'Failed to write readme.txt.', __LINE__ );
}

log( 'Validated README.md and generated readme.txt' );
