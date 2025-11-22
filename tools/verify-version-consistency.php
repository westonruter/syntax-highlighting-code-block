#!/usr/bin/env php
<?php
/**
 * Verify versions referenced in the plugin match.
 *
 * @codeCoverageIgnore
 * @package WestonRuter\VerifyVersionConsistency
 */

// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
// phpcs:disable WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped

namespace WestonRuter\VerifyVersionConsistency;

if ( 'cli' !== php_sapi_name() ) {
	fwrite( STDERR, "Must run from CLI.\n" );
	exit( 1 );
}

$versions = array();

$readme_md = (string) file_get_contents( __DIR__ . '/../README.md' );
if ( ! preg_match( '/\*\*Stable tag:\*\*\s+(?P<version>\S+)/i', $readme_md, $matches ) ) {
	echo "Could not find stable tag in readme.\n";
	exit( 1 );
}
$versions['stable_tag'] = $matches['version'];

if ( ! preg_match( '/^## Changelog\s+### (?P<latest>\d.+)$/m', $readme_md, $matches ) ) {
	echo "Could not find changelog in readme.\n";
	exit( 1 );
}
$versions['latest_changelog_version'] = $matches['latest'];

$bootstrap_file = null;
foreach ( (array) glob( __DIR__ . '/../*.php' ) as $php_file ) {
	echo "$php_file\n";
	$php_file_contents = (string) file_get_contents( (string) $php_file );
	if ( preg_match( '/^ \* Plugin Name\s*:/im', $php_file_contents ) ) {
		$bootstrap_file = (string) $php_file;
		break;
	}
}
if ( null === $bootstrap_file ) {
	echo "Could not locate PHP bootstrap file.\n";
	exit( 1 );
}

$plugin_file = (string) file_get_contents( $bootstrap_file );
if ( ! preg_match( '/\*\s*Version:\s*(?P<version>\d+\.\d+(?:.\d+)?(-\w+)?)/', $plugin_file, $matches ) ) {
	echo "Could not find version in readme metadata.\n";
	exit( 1 );
}
$versions['plugin_metadata'] = $matches['version'];

if ( ! preg_match( '/const VERSION = \'(?P<version>[^\']+)\'/', $plugin_file, $matches ) ) {
	echo "Could not find version in VERSION constant.\n";
	exit( 1 );
}
$versions['version_constant'] = $matches['version'];

echo "Version references:\n";

echo json_encode( $versions, JSON_PRETTY_PRINT ) . "\n";

if ( 1 !== count( array_unique( $versions ) ) ) {
	echo "Error: Not all version references have been updated.\n";
	exit( 1 );
}

if ( ! str_contains( $versions['plugin_metadata'], '-' ) && ! preg_match( '/^\d+\.\d+\.\d+$/', $versions['plugin_metadata'] ) ) {
	printf( "Error: Release version (%s) lacks patch number. For new point releases, supply patch number of 0, such as 0.9.0 instead of 0.9.\n", $versions['plugin_metadata'] );
	exit( 1 );
}
