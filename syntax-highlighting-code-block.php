<?php
/**
 * Plugin Name:  Syntax-highlighting Code Block (with Server-side Rendering)
 * Plugin URI:   https://github.com/westonruter/syntax-highlighting-code-block
 * Description:  Extending the Code block with syntax highlighting rendered on the server, thus being AMP-compatible and having faster frontend performance.
 * Version:      1.3.2-alpha
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

use Exception;
use WP_Block_Type_Registry;
use WP_Error;
use WP_Customize_Manager;
use WP_Styles;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Customize_Color_Control;
use Highlight\Highlighter;
use function HighlightUtilities\splitCodeIntoArray;
use function HighlightUtilities\getAvailableStyleSheets;
use function HighlightUtilities\getThemeBackgroundColor;

const PLUGIN_VERSION = '1.3.2-alpha';

const BLOCK_NAME = 'core/code';

const DEVELOPMENT_MODE = true; // This is automatically rewritten to false during dist build.

const OPTION_NAME = 'syntax_highlighting';

const DEFAULT_THEME = 'default';

const DEFAULT_HIGHLIGHTED_COLOR = '#ddf6ff';

const BLOCK_STYLE_FILTER = 'syntax_highlighting_code_block_style';

const HIGHLIGHTED_LINE_BACKGROUND_COLOR_FILTER = 'syntax_highlighted_line_background_color';

const FRONTEND_STYLE_HANDLE = 'syntax-highlighting-code-block';

const REST_API_NAMESPACE = 'syntax-highlighting-code-block/v1';

/**
 * Add a tint to an RGB color and make it lighter.
 *
 * @param float[] $rgb_array An array representing an RGB color.
 * @param float   $tint      How much of a tint to apply; a number between 0 and 1.
 * @return float[] The new color as an RGB array.
 */
function add_tint_to_rgb( $rgb_array, $tint ) {
	return [
		'r' => $rgb_array['r'] + ( 255 - $rgb_array['r'] ) * $tint,
		'g' => $rgb_array['g'] + ( 255 - $rgb_array['g'] ) * $tint,
		'b' => $rgb_array['b'] + ( 255 - $rgb_array['b'] ) * $tint,
	];
}

/**
 * Get the relative luminance of a color.
 *
 * @link https://en.wikipedia.org/wiki/Relative_luminance
 *
 * @param float[] $rgb_array An array representing an RGB color.
 * @return float A value between 0 and 100 representing the luminance of a color.
 *     The closer to to 100, the higher the luminance is; i.e. the lighter it is.
 */
function get_relative_luminance( $rgb_array ) {
	return 0.2126 * ( $rgb_array['r'] / 255 ) +
		0.7152 * ( $rgb_array['g'] / 255 ) +
		0.0722 * ( $rgb_array['b'] / 255 );
}

/**
 * Check whether or not a given RGB array is considered a "dark theme."
 *
 * @param float[] $rgb_array The RGB array to test.
 * @return bool True if the theme's background has a "dark" luminance.
 */
function is_dark_theme( $rgb_array ) {
	return get_relative_luminance( $rgb_array ) <= 0.6;
}

/**
 * Convert an RGB array to hexadecimal representation.
 *
 * @param float[] $rgb_array The RGB array to convert.
 * @return string A hexadecimal representation.
 */
function get_hex_from_rgb( $rgb_array ) {
	return sprintf(
		'#%02X%02X%02X',
		$rgb_array['r'],
		$rgb_array['g'],
		$rgb_array['b']
	);
}

/**
 * Get the default highlighted line background color.
 *
 * In a dark theme, the background color is decided by adding a 15% tint to the
 * color.
 *
 * In a light theme, a default light blue is used.
 *
 * @param string $theme_name The theme name to get a color for.
 * @return string A hexadecimal value.
 */
function get_default_line_background_color( $theme_name ) {
	require_highlight_php_functions();

	$theme_rgb = getThemeBackgroundColor( $theme_name );

	if ( is_dark_theme( $theme_rgb ) ) {
		return get_hex_from_rgb(
			add_tint_to_rgb( $theme_rgb, 0.15 )
		);
	}

	return DEFAULT_HIGHLIGHTED_COLOR;
}

/**
 * Get an array of all the options tied to this plugin.
 *
 * @return array
 */
function get_plugin_options() {
	$options = get_option( OPTION_NAME, [] );

	$theme_name = isset( $options['theme_name'] ) ? $options['theme_name'] : DEFAULT_THEME;
	return array_merge(
		[
			'theme_name'                        => DEFAULT_THEME,
			'highlighted_line_background_color' => get_default_line_background_color( $theme_name ),
		],
		$options
	);
}

/**
 * Get the single, specified plugin option.
 *
 * @param string $option_name The plugin option name.
 * @return string|null
 */
function get_plugin_option( $option_name ) {
	$options = get_plugin_options();
	if ( array_key_exists( $option_name, $options ) ) {
		return $options[ $option_name ];
	}
	return null;
}

/**
 * Require the highlight.php functions file.
 */
function require_highlight_php_functions() {
	if ( DEVELOPMENT_MODE ) {
		require_once __DIR__ . '/vendor/scrivo/highlight.php/HighlightUtilities/functions.php';
	} else {
		require_once __DIR__ . '/vendor/scrivo/highlight-php/HighlightUtilities/functions.php';
	}
}

/**
 * Initialize plugin.
 *
 * As of Gutenberg 8.3, this must run after `init` priority 10, because at that point the core blocks are registered
 * server-side via `gutenberg_reregister_core_block_types()`.
 *
 * @see gutenberg_reregister_core_block_types()
 * @see https://github.com/WordPress/gutenberg/issues/2751
 * @see https://github.com/WordPress/gutenberg/pull/22491
 */
function init() {
	if ( ! function_exists( 'register_block_type' ) ) {
		return;
	}

	if ( DEVELOPMENT_MODE && ! file_exists( __DIR__ . '/build/index.asset.php' ) ) {
		add_action( 'admin_notices', __NAMESPACE__ . '\print_build_required_admin_notice' );
		return;
	}

	$attributes = [
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

	$registry = WP_Block_Type_Registry::get_instance();

	if ( $registry->is_registered( BLOCK_NAME ) ) {
		$block                  = $registry->get_registered( BLOCK_NAME );
		$block->render_callback = __NAMESPACE__ . '\render_block';
		$block->attributes      = array_merge( $block->attributes, $attributes );
	} else {
		register_block_type(
			BLOCK_NAME,
			[
				'render_callback' => __NAMESPACE__ . '\render_block',
				'attributes'      => $attributes,
			]
		);
	}

	add_action( 'enqueue_block_editor_assets', __NAMESPACE__ . '\enqueue_editor_assets' );
}
add_action( 'init', __NAMESPACE__ . '\init', 100 );

/**
 * Print admin notice when plugin installed from source but no build being performed.
 */
function print_build_required_admin_notice() {
	?>
	<div class="notice notice-error">
		<p>
			<strong><?php esc_html_e( 'Syntax-highlighting Code Block', 'syntax-highlighting-code-block' ); ?>:</strong>
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
	$script_handle = 'syntax-highlighting-code-block-scripts';
	$script_path   = '/build/index.js';
	$style_handle  = 'syntax-highlighting-code-block-styles';
	$style_path    = '/editor-styles.css';
	$script_asset  = require __DIR__ . '/build/index.asset.php';
	$in_footer     = true;

	wp_enqueue_style(
		$style_handle,
		plugins_url( $style_path, __FILE__ ),
		[],
		SCRIPT_DEBUG ? filemtime( plugin_dir_path( __FILE__ ) . $style_path ) : PLUGIN_VERSION
	);

	wp_enqueue_script(
		$script_handle,
		plugins_url( $script_path, __FILE__ ),
		$script_asset['dependencies'],
		$script_asset['version'],
		$in_footer
	);

	wp_set_script_translations( $script_handle, 'syntax-highlighting-code-block' );

	$block = WP_Block_Type_Registry::get_instance()->get_registered( BLOCK_NAME );
	$data  = [
		'name'       => BLOCK_NAME,
		'attributes' => $block->attributes,
		'deprecated' => [
			'selectedLines' => $block->attributes['highlightedLines'],
			'showLines'     => $block->attributes['showLineNumbers'],
		],
	];
	wp_add_inline_script(
		$script_handle,
		sprintf( 'const syntaxHighlightingCodeBlockType = %s;', wp_json_encode( $data ) ),
		'before'
	);

	wp_add_inline_script(
		$script_handle,
		sprintf( 'const syntaxHighlightingCodeBlockLanguageNames = %s;', wp_json_encode( get_language_names() ) ),
		'before'
	);
}

/**
 * Get highlight theme name.
 *
 * @return string Theme name.
 */
function get_theme_name() {
	if ( has_filter( BLOCK_STYLE_FILTER ) ) {
		/**
		 * Filters the style used for the code syntax block.
		 *
		 * The string returned must correspond to the filenames found at <https://github.com/scrivo/highlight.php/tree/master/styles>,
		 * minus the file extension.
		 *
		 * This filter takes precedence over any settings set in the database as an option. Additionally, if this filter
		 * is provided, then a theme selector will not be provided in Customizer.
		 *
		 * @since 1.0.0
		 * @param string $style Style.
		 */
		$style = apply_filters( BLOCK_STYLE_FILTER, DEFAULT_THEME );
	} else {
		$style = get_plugin_option( 'theme_name' );
	}
	return $style;
}

/**
 * Register assets for the frontend.
 *
 * Asset(s) will only be enqueued if needed.
 *
 * @param WP_Styles $styles Styles.
 */
function register_styles( WP_Styles $styles ) {
	$style = get_theme_name();

	$default_style_path = sprintf(
		'vendor/scrivo/%s/styles/%s.css',
		DEVELOPMENT_MODE ? 'highlight.php' : 'highlight-php',
		0 === validate_file( $style ) ? $style : DEFAULT_THEME
	);
	$styles->add(
		FRONTEND_STYLE_HANDLE,
		plugins_url( $default_style_path, __FILE__ ),
		[],
		SCRIPT_DEBUG ? filemtime( plugin_dir_path( __FILE__ ) . $default_style_path ) : PLUGIN_VERSION
	);
}

/**
 * Determines whether styling is enabled.
 *
 * @return bool Styling.
 */
function is_styling_enabled() {
	/**
	 * Filters whether the Syntax-highlighting Code Block's default styling is enabled.
	 *
	 * @param bool $enabled Default styling enabled.
	 */
	return (bool) apply_filters( 'syntax_highlighting_code_block_styling', true );
}

/**
 * Get styles.
 *
 * @param array $attributes Attributes.
 * @return string Attributes.
 */
function get_styles( $attributes ) {
	if ( is_feed() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
		return '';
	}

	if ( ! is_styling_enabled() ) {
		return '';
	}

	if ( ! wp_style_is( FRONTEND_STYLE_HANDLE, 'registered' ) ) {
		register_styles( wp_styles() );
	}

	// Print stylesheet now that we know it will be needed. Note that the stylesheet is not being enqueued at the
	// wp_enqueue_scripts action because this could result in the stylesheet being printed when it would never be used.
	// When a stylesheet is printed in the body it has the additional benefit of not being render-blocking. When
	// a stylesheet is printed the first time, subsequent calls to wp_print_styles() will no-op.
	// Note that the done handles are reset prior to printing so that the stylesheets will be included with each
	// instance of the block. This is the account for a case where a user calls do_blocks() on the content prior to it
	// being printed, which can result in the stylesheets being rendered but never added to the page.
	// Browsers appear to be smart enough to skip loading duplicate references to the same stylesheet.
	ob_start();
	$old_done_handles = wp_styles()->done;
	wp_styles()->done = [];
	wp_styles()->do_items( [ FRONTEND_STYLE_HANDLE ] );
	wp_styles()->done = $old_done_handles;
	$styles           = trim( ob_get_clean() );

	// Include line-number styles if requesting to show lines.
	$styles .= sprintf( '<style>%s</style>', file_get_contents( __DIR__ . '/style.css' ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

	if ( ! empty( $attributes['highlightedLines'] ) ) {
		if ( has_filter( HIGHLIGHTED_LINE_BACKGROUND_COLOR_FILTER ) ) {
			/**
			 * Filters the background color of a highlighted line.
			 *
			 * This filter takes precedence over any settings set in the database as an option. Additionally, if this filter
			 * is provided, then a color selector will not be provided in Customizer.
			 *
			 * @param string $rgb_color An RGB hexadecimal (with the #) to be used as the background color of a highlighted line.
			 *
			 * @since 1.1.5
			 */
			$line_color = apply_filters( HIGHLIGHTED_LINE_BACKGROUND_COLOR_FILTER, get_default_line_background_color( DEFAULT_THEME ) );
		} else {
			$line_color = get_plugin_option( 'highlighted_line_background_color' );
		}

		$styles .= "<style>.hljs > mark.shcb-loc { background-color: $line_color; }</style>";
	}

	return $styles;
}

/**
 * Language names.
 *
 * @return array Mapping slug to name.
 */
function get_language_names() {
	return require __DIR__ . '/language-names.php';
}

/**
 * Inject class names and styles into the
 *
 * @param string $pre_start_tag  The `<pre>` start tag.
 * @param string $code_start_tag The `<code>` start tag.
 * @param array  $attributes     Attributes.
 * @param string $content        Content.
 * @return string Injected markup.
 */
function inject_markup( $pre_start_tag, $code_start_tag, $attributes, $content ) {
	$added_classes = 'hljs';

	if ( $attributes['language'] ) {
		$added_classes .= " language-{$attributes['language']}";
	}

	if ( $attributes['showLineNumbers'] || $attributes['highlightedLines'] ) {
		$added_classes .= ' shcb-code-table';
	}

	if ( $attributes['showLineNumbers'] ) {
		$added_classes .= ' shcb-line-numbers';
	}

	if ( $attributes['wrapLines'] ) {
		$added_classes .= ' shcb-wrap-lines';
	}

	$code_start_tag = preg_replace(
		'/(<code[^>]*\sclass=")/',
		'$1' . esc_attr( $added_classes ) . ' ',
		$code_start_tag,
		1,
		$count
	);
	if ( 0 === $count ) {
		$code_start_tag = preg_replace(
			'/(?<=<code\b)/',
			sprintf( ' class="%s"', esc_attr( $added_classes ) ),
			$code_start_tag,
			1
		);
	}

	$end_tags = '</code></span>';

	if ( ! empty( $attributes['language'] ) ) {
		$language_names = get_language_names();
		$language_name  = isset( $language_names[ $attributes['language'] ] ) ? $language_names[ $attributes['language'] ] : $attributes['language'];

		$element_id = wp_unique_id( 'shcb-language-' );

		// Add the language info to markup with semantic label.
		$end_tags .= sprintf(
			'<small class="shcb-language" id="%s"><span class="shcb-language__label">%s</span> <span class="shcb-language__name">%s</span> <span class="shcb-language__paren">(</span><span class="shcb-language__slug">%s</span><span class="shcb-language__paren">)</span></small>',
			esc_attr( $element_id ),
			esc_html__( 'Code language:', 'syntax-highlighting-code-block' ),
			esc_html( $language_name ),
			esc_html( $attributes['language'] )
		);

		// Also include the language in data attributes on the root <pre> element for maximum styling flexibility.
		$pre_start_tag = str_replace(
			'>',
			sprintf(
				' aria-describedby="%s" data-shcb-language-name="%s" data-shcb-language-slug="%s">',
				esc_attr( $element_id ),
				esc_attr( $language_name ),
				esc_attr( $attributes['language'] )
			),
			$pre_start_tag
		);
	}
	$end_tags .= '</pre>';

	return $pre_start_tag . get_styles( $attributes ) . '<span>' . $code_start_tag . $content . $end_tags;
}

/**
 * Render code block.
 *
 * @param array  $attributes Attributes.
 * @param string $content    Content.
 * @return string Highlighted content.
 */
function render_block( $attributes, $content ) {
	$pattern  = '(?P<pre_start_tag><pre\b[^>]*?>)(?P<code_start_tag><code\b[^>]*?>)';
	$pattern .= '(?P<content>.*)';
	$pattern .= '</code></pre>';

	if ( ! preg_match( '#^\s*' . $pattern . '\s*$#s', $content, $matches ) ) {
		return $content;
	}

	// Migrate legacy attribute names.
	if ( isset( $attributes['selectedLines'] ) ) {
		$attributes['highlightedLines'] = $attributes['selectedLines'];
		unset( $attributes['selectedLines'] );
	}
	if ( isset( $attributes['showLines'] ) ) {
		$attributes['showLineNumbers'] = $attributes['showLines'];
		unset( $attributes['showLines'] );
	}

	/**
	 * Filters the list of languages that are used for auto-detection.
	 *
	 * @param string[] $auto_detect_language Auto-detect languages.
	 */
	$auto_detect_languages = apply_filters( 'syntax_highlighting_code_block_auto_detect_languages', [] );

	$transient_key = 'syntax-highlighted-' . md5( wp_json_encode( $attributes ) . implode( '', $auto_detect_languages ) . $matches['content'] . PLUGIN_VERSION );
	$highlighted   = get_transient( $transient_key );

	if ( ! DEVELOPMENT_MODE && $highlighted && isset( $highlighted['content'] ) ) {
		return inject_markup( $matches['pre_start_tag'], $matches['code_start_tag'], $highlighted['attributes'], $highlighted['content'] );
	}

	try {
		if ( ! class_exists( '\Highlight\Autoloader' ) ) {
			if ( DEVELOPMENT_MODE ) {
				require_once __DIR__ . '/vendor/scrivo/highlight.php/Highlight/Autoloader.php';
			} else {
				require_once __DIR__ . '/vendor/scrivo/highlight-php/Highlight/Autoloader.php';
			}
			spl_autoload_register( 'Highlight\Autoloader::load' );
		}

		$highlighter = new Highlighter();
		if ( ! empty( $auto_detect_languages ) ) {
			$highlighter->setAutodetectLanguages( $auto_detect_languages );
		}

		$language = $attributes['language'];
		$content  = html_entity_decode( $matches['content'], ENT_QUOTES );

		// Convert from Prism.js languages names.
		if ( 'clike' === $language ) {
			$language = 'cpp';
		} elseif ( 'git' === $language ) {
			$language = 'diff'; // Best match.
		} elseif ( 'markup' === $language ) {
			$language = 'xml';
		}

		if ( $language ) {
			$r = $highlighter->highlight( $language, $content );
		} else {
			$r = $highlighter->highlightAuto( $content );
		}
		$attributes['language'] = $r->language;

		$content = $r->value;
		if ( $attributes['showLineNumbers'] || $attributes['highlightedLines'] ) {
			require_highlight_php_functions();

			$highlighted_lines = parse_highlighted_lines( $attributes['highlightedLines'] );
			$lines             = splitCodeIntoArray( $content );
			$content           = '';

			// We need to wrap the line of code twice in order to let out `white-space: pre` CSS setting to be respected
			// by our `table-row`.
			foreach ( $lines as $i => $line ) {
				$tag_name = in_array( $i, $highlighted_lines, true ) ? 'mark' : 'span';
				$content .= "<$tag_name class='shcb-loc'><span>$line\n</span></$tag_name>";
			}
		}

		if ( ! DEVELOPMENT_MODE ) {
			set_transient( $transient_key, compact( 'content', 'attributes' ), MONTH_IN_SECONDS );
		}

		return inject_markup( $matches['pre_start_tag'], $matches['code_start_tag'], $attributes, $content );
	} catch ( Exception $e ) {
		return sprintf(
			'<!-- %s(%s): %s -->%s',
			get_class( $e ),
			$e->getCode(),
			str_replace( '--', '', $e->getMessage() ),
			$content
		);
	}
}

/**
 * Parse the highlighted line syntax from the front-end and return an array of highlighted line numbers.
 *
 * @param string $highlighted_lines The highlighted line syntax.
 * @return int[]
 */
function parse_highlighted_lines( $highlighted_lines ) {
	$highlighted_line_numbers = [];

	if ( ! $highlighted_lines || empty( trim( $highlighted_lines ) ) ) {
		return $highlighted_line_numbers;
	}

	$ranges = explode( ',', preg_replace( '/\s/', '', $highlighted_lines ) );

	foreach ( $ranges as $chunk ) {
		if ( strpos( $chunk, '-' ) !== false ) {
			$range = explode( '-', $chunk );

			if ( count( $range ) === 2 ) {
				for ( $i = (int) $range[0]; $i <= (int) $range[1]; $i++ ) {
					$highlighted_line_numbers[] = $i - 1;
				}
			}
		} else {
			$highlighted_line_numbers[] = (int) $chunk - 1;
		}
	}

	return $highlighted_line_numbers;
}

/**
 * Validate the given stylesheet name against available stylesheets.
 *
 * @param WP_Error $validity Validator object.
 * @param string   $input    Incoming theme name.
 * @return mixed
 */
function validate_theme_name( $validity, $input ) {
	require_highlight_php_functions();

	$themes = getAvailableStyleSheets();

	if ( ! in_array( $input, $themes, true ) ) {
		$validity->add( 'invalid_theme', __( 'Unrecognized theme', 'syntax-highlighting-code-block' ) );
	}

	return $validity;
}

/**
 * Add plugin settings to Customizer.
 *
 * @param WP_Customize_Manager $wp_customize The Customizer object.
 */
function customize_register( $wp_customize ) {
	if ( has_filter( BLOCK_STYLE_FILTER ) && has_filter( HIGHLIGHTED_LINE_BACKGROUND_COLOR_FILTER ) ) {
		return;
	}

	if ( ! is_styling_enabled() ) {
		return;
	}

	require_highlight_php_functions();

	$theme_name = get_theme_name();

	if ( ! has_filter( BLOCK_STYLE_FILTER ) ) {
		$themes = getAvailableStyleSheets();
		sort( $themes );
		$choices = array_combine( $themes, $themes );

		$setting = $wp_customize->add_setting(
			'syntax_highlighting[theme_name]',
			[
				'type'              => 'option',
				'default'           => DEFAULT_THEME,
				'validate_callback' => __NAMESPACE__ . '\validate_theme_name',
			]
		);

		// Obtain the working theme name in the changeset.
		$theme_name = $setting->post_value( $theme_name );

		$wp_customize->add_control(
			'syntax_highlighting[theme_name]',
			[
				'type'        => 'select',
				'section'     => 'colors',
				'label'       => __( 'Syntax Highlighting Theme', 'syntax-highlighting-code-block' ),
				'description' => __( 'Preview the theme by navigating to a page with a Code block to see the different themes in action.', 'syntax-highlighting-code-block' ),
				'choices'     => $choices,
			]
		);
	}

	if ( ! has_filter( HIGHLIGHTED_LINE_BACKGROUND_COLOR_FILTER ) ) {
		$default_color = strtolower( get_default_line_background_color( $theme_name ) );
		$wp_customize->add_setting(
			'syntax_highlighting[highlighted_line_background_color]',
			[
				'type'              => 'option',
				'default'           => $default_color,
				'sanitize_callback' => 'sanitize_hex_color',
			]
		);
		$wp_customize->add_control(
			new WP_Customize_Color_Control(
				$wp_customize,
				'syntax_highlighting[highlighted_line_background_color]',
				[
					'section'     => 'colors',
					'settings'    => 'syntax_highlighting[highlighted_line_background_color]',
					'label'       => __( 'Highlighted Line Color', 'syntax-highlighting-code-block' ),
					'description' => __( 'The background color of a highlighted line in a Code block.', 'syntax-highlighting-code-block' ),
				]
			)
		);

		// Add the script to synchronize the default highlighting line color with the selected theme.
		if ( ! has_filter( BLOCK_STYLE_FILTER ) ) {
			add_action( 'customize_controls_enqueue_scripts', __NAMESPACE__ . '\enqueue_customize_scripts' );
		}
	}
}
add_action( 'customize_register', __NAMESPACE__ . '\customize_register', 100 );

/**
 * Enqueue scripts for Customizer.
 */
function enqueue_customize_scripts() {
	$script_handle = 'syntax-highlighting-code-block-customize-controls';
	$script_path   = '/build/customize-controls.js';
	$script_asset  = require __DIR__ . '/build/customize-controls.asset.php';
	$in_footer     = true;

	wp_enqueue_script(
		$script_handle,
		plugins_url( $script_path, __FILE__ ),
		array_merge( [ 'customize-controls' ], $script_asset['dependencies'] ),
		$script_asset['version'],
		$in_footer
	);
}

/**
 * Register REST endpoint.
 */
function register_rest_endpoint() {
	register_rest_route(
		REST_API_NAMESPACE,
		'/highlighted-line-background-color/(?P<theme_name>[^/]+)',
		[
			'methods'             => WP_REST_Server::READABLE,
			'permission_callback' => static function () {
				return current_user_can( 'customize' );
			},
			'callback'            => static function ( WP_REST_Request $request ) {
				$theme_name = $request['theme_name'];
				$validity   = validate_theme_name( new WP_Error(), $theme_name );
				if ( $validity->errors ) {
					return $validity;
				}
				return new WP_REST_Response( get_default_line_background_color( $theme_name ) );
			},
		]
	);
}
add_action( 'rest_api_init', __NAMESPACE__ . '\register_rest_endpoint' );
