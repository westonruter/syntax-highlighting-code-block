<?php
/**
 * Plugin Name:  Syntax-highlighting Code Block (with Server-side Rendering)
 * Plugin URI:   https://github.com/westonruter/syntax-highlighting-code-block
 * Description:  Extending the Code block with syntax highlighting rendered on the server, thus being AMP-compatible and having faster frontend performance.
 * Version:      1.5.0
 * Author:       Weston Ruter
 * Author URI:   https://weston.ruter.net/
 * License:      GPL2
 * License URI:  https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:  syntax-highlighting-code-block
 * Requires PHP: 7.4
 *
 * @package Syntax_Highlighting_Code_Block
 */

namespace Syntax_Highlighting_Code_Block;

const PLUGIN_VERSION = '1.5.0';

const PLUGIN_MAIN_FILE = __FILE__;

const PLUGIN_DIR = __DIR__;

const BLOCK_NAME = 'core/code';

const DEVELOPMENT_MODE = true; // This is automatically rewritten to false during dist build.

const OPTION_NAME = 'syntax_highlighting';

const DEFAULT_THEME = 'default';

const DEFAULT_HIGHLIGHTED_COLOR = '#ddf6ff';

const BLOCK_STYLE_FILTER = 'syntax_highlighting_code_block_style';

const HIGHLIGHTED_LINE_BACKGROUND_COLOR_FILTER = 'syntax_highlighted_line_background_color';

const THEME_STYLE_HANDLE = 'syntax-highlighting-code-block-theme';

const BLOCK_STYLE_HANDLE = 'syntax-highlighting-code-block';

const STYLE_HANDLES = [ THEME_STYLE_HANDLE, BLOCK_STYLE_HANDLE ];

const REST_API_NAMESPACE = 'syntax-highlighting-code-block/v1';

const EDITOR_SCRIPT_HANDLE = 'syntax-highlighting-code-block-scripts';

const EDITOR_STYLE_HANDLE = 'syntax-highlighting-code-block-styles';

const ATTRIBUTE_SCHEMA = [
	'language'         => [
		'type'    => 'string',
		'default' => '',
	],
	'highlightedLines' => [
		'type'    => 'string',
		'default' => '',
	],
	'showLineNumbers'  => [
		'type'    => 'boolean',
		'default' => false,
	],
	'wrapLines'        => [
		'type'    => 'boolean',
		'default' => false,
	],
];

require_once __DIR__ . '/inc/functions.php';

add_action( 'plugins_loaded', __NAMESPACE__ . '\boot' );
