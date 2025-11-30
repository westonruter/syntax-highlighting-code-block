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

$package_json_contents = file_get_contents( __DIR__ . '/../package.json' );
if ( false === $package_json_contents ) {
	echo "Failed to read package.json\n";
	exit( 1 );
}
$package_json = json_decode( $package_json_contents, true );
if ( ! is_array( $package_json ) ) {
	echo 'Failed to parse package.json: ' . ( JSON_ERROR_NONE === json_last_error() ? 'Not an array' : json_last_error_msg() ) . "\n";
	exit( 1 );
}
if ( ! array_key_exists( 'version', $package_json ) ) {
	echo "The 'version' key is missing in package.json\n";
	exit( 1 );
}
$versions['package_json'] = $package_json['version'];

$readme_md = (string) file_get_contents( __DIR__ . '/../README.md' );
if ( ! preg_match( '/\*\*Stable tag:\*\*\s+(?P<version>\S+)/i', $readme_md, $matches ) ) {
	echo "Could not find stable tag in readme.\n";
	exit( 1 );
}
$versions['stable_tag'] = $matches['version'];

if ( ! preg_match( '/^## Changelog\s+(.+)$/m', $readme_md, $matches ) ) {
	echo "Could not find changelog in readme.\n";
	exit( 1 );
}
$first_changelog_line = $matches[1];
if ( preg_match( '/^### (?P<latest>\d.+)/', $first_changelog_line, $matches ) ) {
	$versions['latest_changelog_version'] = $matches['latest'];
} elseif ( preg_match( '/\[.+?]\(.+?\)/', $first_changelog_line, $matches ) ) {
	echo "Notice: The full changelog appears to not be part of the readme. It may be external: {$matches[0]}\n";
} else {
	echo "Could not identify first item of changelog in readme.\n";
	exit( 1 );
}

$bootstrap_file = null;
foreach ( (array) glob( __DIR__ . '/../*.php' ) as $php_file ) {
	$php_file_contents = (string) file_get_contents( (string) $php_file );
	if ( preg_match( '/^ \* Plugin Name\s*:/im', $php_file_contents ) ) {
		$bootstrap_file = (string) $php_file;
		echo "Located bootstrap file: $bootstrap_file\n";
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

if ( ! preg_match( '/const (?:PLUGIN_)?VERSION = \'(?P<version>[^\']+)\'/', $plugin_file, $matches ) ) {
	echo "Could not find version in PLUGIN_VERSION/VERSION constant.\n";
	exit( 1 );
}
$versions['version_constant'] = $matches['version'];

echo "Version references:\n";
echo json_encode( $versions, JSON_PRETTY_PRINT ) . "\n";

$versions_without_stable_tag = $versions;
unset( $versions_without_stable_tag['stable_tag'] );
if ( 1 !== count( array_unique( $versions_without_stable_tag ) ) ) {
	echo "Error: Not all version references have been updated.\n";
	exit( 1 );
}

if ( $versions['stable_tag'] !== $versions['plugin_metadata'] ) {
	if ( false === strpos( $versions['plugin_metadata'], '-' ) ) {
		echo "Expected plugin version ({$versions['plugin_metadata']}) to match stable tag {$versions['stable_tag']} when the plugin version lacks a prerelease tag.\n";
		exit( 1 );
	}
	if ( ! version_compare( $versions['stable_tag'], $versions['plugin_metadata'], '<' ) ) {
		echo "Expected plugin version ({$versions['plugin_metadata']}) to be greater than the stable tag {$versions['stable_tag']} due to the prerelease tag.\n";
		exit( 1 );
	}
}

if ( false === strpos( $versions['plugin_metadata'], '-' ) && ! preg_match( '/^\d+\.\d+\.\d+$/', $versions['plugin_metadata'] ) ) {
	printf( "Error: Release version (%s) lacks patch number. For new point releases, supply patch number of 0, such as 0.9.0 instead of 0.9.\n", $versions['plugin_metadata'] );
	exit( 1 );
}
