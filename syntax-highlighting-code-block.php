<?php
/**
 * Plugin Name:  Syntax-highlighting Code Block (with Server-side Rendering)
 * Plugin URI:   https://github.com/westonruter/syntax-highlighting-code-block
 * Description:  Extending the Code block with syntax highlighting rendered on the server, thus being AMP-compatible and having faster frontend performance.
 * Version:      1.0.0
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

const PLUGIN_VERSION = '1.0.0';

const FRONTEND_STYLE_HANDLE = 'syntax-highlighting-code-block';

/**
 * Initialize plugin.
 */
function init() {
	if ( ! function_exists( 'register_block_type' ) ) {
		return;
	}

	register_block_type(
		'core/code',
		[
			'render_callback' => __NAMESPACE__ . '\render_block',
		]
	);
}
add_action( 'plugins_loaded', __NAMESPACE__ . '\init' );

/**
 * Enqueue assets for editor.
 */
function enqueue_editor_assets() {
	$in_footer = true;

	$htm_path = '/node_modules/htm/dist/htm.js';
	wp_register_script(
		'htm',
		plugins_url( $htm_path, __FILE__ ),
		[],
		SCRIPT_DEBUG ? filemtime( plugin_dir_path( __FILE__ ) . $htm_path ) : PLUGIN_VERSION,
		$in_footer
	);

	$handle     = 'syntax-highlighting-code-block';
	$block_path = '/syntax-highlighting-code-block.js';
	wp_enqueue_script(
		$handle,
		plugins_url( $block_path, __FILE__ ),
		[ 'wp-blocks', 'wp-hooks', 'wp-element', 'wp-i18n', 'htm' ],
		SCRIPT_DEBUG ? filemtime( plugin_dir_path( __FILE__ ) . $block_path ) : PLUGIN_VERSION,
		$in_footer
	);

	wp_add_inline_script(
		$handle,
		sprintf( 'const codeSyntaxBlockLanguages = %s;', wp_json_encode( get_languages() ) )
	);

	wp_set_script_translations( $handle, 'syntax-highlighting-code-block' );
}
add_action( 'enqueue_block_editor_assets', __NAMESPACE__ . '\enqueue_editor_assets' );

/**
 * Get languages.
 *
 * @return array Languages.
 */
function get_languages() {
	$language_names = [
		'bash'       => __( 'Bash (shell)', 'syntax-highlighting-code-block' ),
		'cpp'        => __( 'C-like', 'syntax-highlighting-code-block' ),
		'css'        => __( 'CSS', 'syntax-highlighting-code-block' ),
		'diff'       => __( 'Diff', 'syntax-highlighting-code-block' ),
		'dns'        => __( 'DNS', 'syntax-highlighting-code-block' ),
		'dockerfile' => __( 'Dockerfile', 'syntax-highlighting-code-block' ),
		'go'         => __( 'Go (golang)', 'syntax-highlighting-code-block' ),
		'handlebars' => __( 'Handlebars', 'syntax-highlighting-code-block' ),
		'http'       => __( 'HTTP', 'syntax-highlighting-code-block' ),
		'java'       => __( 'Java', 'syntax-highlighting-code-block' ),
		'javascript' => __( 'JavaScript (JSX)', 'syntax-highlighting-code-block' ),
		'json'       => __( 'JSON', 'syntax-highlighting-code-block' ),
		'less'       => __( 'LESS', 'syntax-highlighting-code-block' ),
		'makefile'   => __( 'Makefile', 'syntax-highlighting-code-block' ),
		'markdown'   => __( 'Markdown', 'syntax-highlighting-code-block' ),
		'nginx'      => __( 'Nginx', 'syntax-highlighting-code-block' ),
		'perl'       => __( 'Perl', 'syntax-highlighting-code-block' ),
		'php'        => __( 'PHP', 'syntax-highlighting-code-block' ),
		'protobuf'   => __( 'Protobuf', 'syntax-highlighting-code-block' ),
		'python'     => __( 'Python', 'syntax-highlighting-code-block' ),
		'scss'       => __( 'SCSS', 'syntax-highlighting-code-block' ),
		'shell'      => __( 'Shell', 'syntax-highlighting-code-block' ),
		'sql'        => __( 'SQL', 'syntax-highlighting-code-block' ),
		'twig'       => __( 'Twig', 'syntax-highlighting-code-block' ),
		'typescript' => __( 'TypeScript', 'syntax-highlighting-code-block' ),
		'xml'        => __( 'HTML/Markup', 'syntax-highlighting-code-block' ),
		'yaml'       => __( 'YAML', 'syntax-highlighting-code-block' ),
	];

	$languages = [];
	foreach ( glob( __DIR__ . '/vendor/scrivo/highlight.php/Highlight/languages/*.json' ) as $language_file ) {
		$basename = basename( $language_file, '.json' );

		$languages[ $basename ] = [
			'label' => isset( $language_names[ $basename ] ) ? $language_names[ $basename ] : $basename,
			'value' => $basename,
		];
	}
	usort(
		$languages,
		function( $a, $b ) {
			return strcmp( strtolower( $a['label'] ), strtolower( $b['label'] ) );
		}
	);

	return $languages;
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
		FRONTEND_STYLE_HANDLE,
		plugins_url( $default_style_path, __FILE__ ),
		[],
		SCRIPT_DEBUG ? filemtime( plugin_dir_path( __FILE__ ) . $default_style_path ) : PLUGIN_VERSION
	);
}
add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\register_frontend_assets' );

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

	// Enqueue the style now that we know it will be needed.
	wp_enqueue_style( FRONTEND_STYLE_HANDLE );

	$inject_classes = function( $start_tags, $language ) {
		$added_classes = "hljs language-$language";
		$start_tags    = preg_replace(
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

	$transient_key = 'syntax-highlighting-code-block-' . md5( $attributes['language'] . $matches['code'] ) . '-v' . PLUGIN_VERSION;
	$highlighted   = get_transient( $transient_key );

	if ( $highlighted && isset( $highlighted['code'] ) ) {
		if ( isset( $highlighted['language'] ) ) {
			$matches['before'] = $inject_classes( $matches['before'], $highlighted['language'] );
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

		set_transient( $transient_key, compact( 'code', 'language' ), MONTH_IN_SECONDS );

		$matches['before'] = $inject_classes( $matches['before'], $highlighted['language'] );

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
