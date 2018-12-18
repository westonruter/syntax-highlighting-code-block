<?php
/**
 * Plugin Name:  Code Syntax Block (with Server-Side Highlighting)
 * Plugin URI:   https://github.com/westonruter/code-syntax-block
 * Description:  A plugin to extend Gutenberg code block with syntax highlighting.
 * Version:      0.4.0
 * Author:       Weston Ruter
 * Author URI:   https://weston.ruter.net/
 * License:      GPL2
 * License URI:  https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:  code-syntax-block
 * Requires PHP: 5.4
 *
 * @package Code_Syntax_Block
 */

namespace Code_Syntax_Block;

/**
 * Load text domain.
 */
function init() {
	if ( ! function_exists( 'register_block_type' ) ) {
		return;
	}

	load_plugin_textdomain( 'code-syntax-block', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

	register_block_type( 'core/code', array(
		'render_callback' => __NAMESPACE__ . '\render_block',
	) );
}
add_action( 'plugins_loaded', __NAMESPACE__ . '\init' );

/**
 * Enqueue assets for editor portion of Gutenberg
 */
function enqueue_editor_assets() {
	wp_register_script(
		'htm',
		plugins_url( $block_path, __FILE__ ) . '/node_modules/htm/dist/htm.js',
		array(),
		false
	);

	$handle = 'code-syntax-block';
	wp_enqueue_script(
		$handle,
		plugins_url( $block_path, __FILE__ ) . '/code-syntax-block.js',
		array( 'wp-blocks', 'wp-hooks', 'wp-element', 'wp-i18n', 'htm' ),
		filemtime( plugin_dir_path( __FILE__ ) . $block_path )
	);

	wp_set_script_translations( $handle, 'code-syntax-block' );
}
add_action( 'enqueue_block_editor_assets', __NAMESPACE__ . '\enqueue_editor_assets' );

/**
 * Enqueue assets for viewing posts.
 *
 * @todo This should only be enqueued if the block is actually on the page!
 */
function enqueue_frontend_assets() {
	// Files.
	$default_style_path = 'vendor/scrivo/highlight.php/styles/default.css';

	// Enqueue prism style.
	wp_enqueue_style(
		'code-syntax-block-hljs-default',
		plugins_url( $default_style_path, __FILE__ ),
		array(),
		filemtime( plugin_dir_path( __FILE__ ) . $default_style_path )
	);
}
add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\enqueue_frontend_assets' );

/**
 * Render code block.
 *
 * @param array  $attributes Attributes.
 * @param string $content    Content.
 * @return string Highlighted content.
 */
function render_block( $attributes, $content ) {
	$pattern  = '(?P<before><pre.*?><code.*?>)';
	$pattern .= '(?P<code>.*)';
	$after    = '</code></pre>';
	$pattern .= $after;

	if ( ! preg_match( '#^\s*' . $pattern . '\s*$#s', $content, $matches ) ) {
		return $content;
	}

	if ( ! isset( $attributes['language'] ) ) {
		$attributes['language'] = '';
	}

	$inject_language_class = function( $start_tags, $language ) {
		$start_tags = preg_replace(
			'/(<code[^>]*class=")/',
			'$1' . esc_attr( $language . ' ' ),
			$start_tags,
			1,
			$count
		);
		if ( 0 === $count ) {
			$start_tags = preg_replace(
				'/(?<=<code)(?=>)/',
				sprintf( ' class="%s"', esc_attr( "language-$language" ) ),
				$start_tags,
				1
			);
		}
		return $start_tags;
	};

	$transient_key = 'code-syntax-block-' . md5( $attributes['language'] . $matches['code'] ) . '-v1';
	$highlighted   = get_transient( $transient_key );

	if ( $highlighted && isset( $highlighted['code'] ) ) {
		if ( isset( $highlighted['language'] ) ) {
			$matches['before'] = $inject_language_class( $matches['before'], $highlighted['language'] );
		}
		return $matches['before'] . $highlighted['code'] . $after;
	}

	try {
		if ( ! class_exists( '\Highlight\Autoloader' ) ) {
			require_once __DIR__ . '/vendor/scrivo/highlight.php/Highlight/Autoloader.php';
			spl_autoload_register( 'Highlight\Autoloader::load' );
		}

		$highlighter = new \Highlight\Highlighter();
		$language    = $attributes['language'];
		$code        = html_entity_decode( $matches['code'], ENT_QUOTES );

		// Convert from Prism.js languages names.
		if ( 'clike' === $language ) {
			$language = 'cpp';
		} elseif ( 'git' === $language ) {
			$language = 'diff'; // Best match.
		} elseif ( 'markup' === $language ) {
			$language = 'xml';
		}

		if ( $language ) {
			$r = $highlighter->highlight( $language, $code );
		} else {
			$r = $highlighter->highlightAuto( $code );
		}

		$code        = $r->value;
		$language    = $r->language;
		$highlighted = compact( 'code', 'language' );

		set_transient( $transient_key, compact( 'code', 'language' ) );

		$matches['before'] = $inject_language_class( $matches['before'], $highlighted['language'] );

		return $matches['before'] . $code . $after;
	} catch ( \Exception $e ) {
		return sprintf(
			'<!-- %s(%s): %s -->%s',
			get_class( $e ),
			$e->getCode(),
			str_replace( '--', '', $e->getMessage() ),
			$content
		);
	}
}
