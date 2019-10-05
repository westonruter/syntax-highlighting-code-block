<?php
/**
 * Plugin Name:  Syntax-highlighting Code Block (with Server-side Rendering)
 * Plugin URI:   https://github.com/westonruter/syntax-highlighting-code-block
 * Description:  Extending the Code block with syntax highlighting rendered on the server, thus being AMP-compatible and having faster frontend performance.
 * Version:      1.0.2
 * Author:       Weston Ruter
 * Author URI:   https://weston.ruter.net/
 * License:      GPL2
 * License URI:  https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:  syntax-highlighting-code-block
 * Requires PHP: 5.6
 *
 * @package Syntax_Highlighting_Code_Block
 */

namespace Syntax_Highlighting_Code_Block;

const PLUGIN_VERSION = '1.0.2';

const DEVELOPMENT_MODE = true; // This is automatically rewritten to false during dist build.

const FRONTEND_HIGHLIGHT_STYLE_HANDLE = 'syntax-highlighting-code-block';

const FRONTEND_PLUGIN_STYLE_HANDLE = 'syntax-highlighting-plugin-styles';

/**
 * Get path to script deps file.
 *
 * @return string Path.
 */
function get_script_deps_path() {
	return __DIR__ . '/build/index.deps.json';
}

/**
 * Initialize plugin.
 */
function init() {
	if ( ! function_exists( 'register_block_type' ) ) {
		return;
	}

	if ( DEVELOPMENT_MODE && ! file_exists( get_script_deps_path() ) ) {
		add_action( 'admin_notices', __NAMESPACE__ . '\print_build_required_admin_notice' );
		return;
	}

	register_block_type(
		'core/code',
		[
			'render_callback' => __NAMESPACE__ . '\render_block',
		]
	);

	add_action( 'enqueue_block_editor_assets', __NAMESPACE__ . '\enqueue_editor_assets' );
	add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\register_frontend_assets' );
}
add_action( 'plugins_loaded', __NAMESPACE__ . '\init' );

/**
 * Print admin notice when plugin installed from source but no build being performed.
 */
function print_build_required_admin_notice() {
	?>
	<div class="notice notice-error">
		<p>
			<strong><?php esc_html_e( 'Syntax-highlighting Code Block', 'amp' ); ?>:</strong>
			<?php
			echo wp_kses_post(
				sprintf(
					/* translators: %s is the command to run */
					__( 'Unable to initialize plugin due to being installed from source without running a build. Please run %s', 'syntax-highlighting-code-block' ),
					'<code>composer install &amp;&amp; npm install &amp;&amp; npm run build</code>'
				)
			);
			?>
		</p>
	</div>
	<?php
}

/**
 * Enqueue assets for editor.
 */
function enqueue_editor_assets() {
	$handle      = 'syntax-highlighting-code-block';
	$script_path = '/build/index.js';
	$script_deps = json_decode( file_get_contents( get_script_deps_path() ), false ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	$in_footer   = true;

	wp_enqueue_script(
		$handle,
		plugins_url( $script_path, __FILE__ ),
		$script_deps,
		DEVELOPMENT_MODE ? filemtime( plugin_dir_path( __FILE__ ) . $script_path ) : PLUGIN_VERSION,
		$in_footer
	);

	wp_set_script_translations( $handle, 'syntax-highlighting-code-block' );
}

/**
 * Register assets for the frontend.
 *
 * Asset(s) will only be enqueued if needed.
 */
function register_frontend_assets() {
	/**
	 * Filters the style used for the code syntax block.
	 *
	 * The string returned must correspond to the filenames found at <https://github.com/scrivo/highlight.php/tree/master/styles>,
	 * minus the file extension.
	 *
	 * @since 1.0.0
	 * @param string $style Style.
	 */
	$style = apply_filters( 'syntax_highlighting_code_block_style', 'default' );

	$default_style_path = sprintf( 'vendor/scrivo/highlight.php/styles/%s.css', sanitize_key( $style ) );
	wp_register_style(
		FRONTEND_HIGHLIGHT_STYLE_HANDLE,
		plugins_url( $default_style_path, __FILE__ ),
		[],
		SCRIPT_DEBUG ? filemtime( plugin_dir_path( __FILE__ ) . $default_style_path ) : PLUGIN_VERSION
	);

	wp_register_style(
		FRONTEND_PLUGIN_STYLE_HANDLE,
		plugins_url( 'index.css', __FILE__ ),
		[],
		SCRIPT_DEBUG ? filemtime( plugin_dir_path( __FILE__ ) . 'index.css' ) : PLUGIN_VERSION
	);
}

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

	if ( ! isset( $attributes['showLines'] ) ) {
		$attributes['showLines'] = false;
	}

	// Enqueue the style now that we know it will be needed.
	wp_enqueue_style( FRONTEND_HIGHLIGHT_STYLE_HANDLE );
	wp_enqueue_style( FRONTEND_PLUGIN_STYLE_HANDLE );

	$inject_classes = function( $start_tags, $language, $show_lines ) {
		$added_classes = "hljs language-$language";

		if ( $show_lines ) {
			$added_classes .= ' line-numbers';
		}

		$start_tags = preg_replace(
			'/(<code[^>]*class=")/',
			'$1 ' . esc_attr( $added_classes ),
			$start_tags,
			1,
			$count
		);
		if ( 0 === $count ) {
			$start_tags = preg_replace(
				'/(?<=<code)(?=>)/',
				sprintf( ' class="%s"', esc_attr( $added_classes ) ),
				$start_tags,
				1
			);
		}
		return $start_tags;
	};

	$transient_key = 'syntax-highlighting-code-block-' . md5( $attributes['showLines'] . $attributes['language'] . $matches['code'] ) . '-v' . PLUGIN_VERSION;
	$highlighted   = get_transient( $transient_key );

	if ( $highlighted && isset( $highlighted['code'] ) ) {
		if ( isset( $highlighted['language'] ) ) {
			$matches['before'] = $inject_classes( $matches['before'], $highlighted['language'], $highlighted['show_lines'] );
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

		$code       = $r->value;
		$language   = $r->language;
		$show_lines = $attributes['showLines'];

		if ( $show_lines ) {
			require_once __DIR__ . '/vendor/scrivo/highlight.php/HighlightUtilities/functions.php';

			$lines = \HighlightUtilities\splitCodeIntoArray( $code );
			$code  = '';

			// We need to wrap the line of code twice in order to let out `white-space: pre` CSS setting to be respected
			// by our `table-row`.
			foreach ( $lines as $line ) {
				$code .= sprintf( '<div class="loc"><span>%s</span></div>%s', $line, PHP_EOL );
			}
		}

		$highlighted = compact( 'code', 'language', 'show_lines' );

		set_transient( $transient_key, compact( 'code', 'language', 'show_lines' ), MONTH_IN_SECONDS );

		$matches['before'] = $inject_classes( $matches['before'], $highlighted['language'], $highlighted['show_lines'] );

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
