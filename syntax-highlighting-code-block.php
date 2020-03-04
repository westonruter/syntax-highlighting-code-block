<?php
/**
 * Plugin Name:  Syntax-highlighting Code Block (with Server-side Rendering)
 * Plugin URI:   https://github.com/westonruter/syntax-highlighting-code-block
 * Description:  Extending the Code block with syntax highlighting rendered on the server, thus being AMP-compatible and having faster frontend performance.
 * Version:      1.1.4
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
use WP_Error;
use WP_Customize_Manager;
use WP_Styles;

const PLUGIN_VERSION = '1.1.4';

const DEVELOPMENT_MODE = true; // This is automatically rewritten to false during dist build.

const OPTION_NAME = 'syntax_highlighting';

const DEFAULT_THEME = 'default';

const BLOCK_STYLE_FILTER = 'syntax_highlighting_code_block_style';

const FRONTEND_STYLE_HANDLE = 'syntax-highlighting-code-block';

/**
 * Add a tint to an RGB color and make it lighter.
 *
 * @param float[] $rgb_array An array representing an RGB color.
 * @param float   $tint      How much of a tint to apply; a number between 0 and 1.
 *
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
 * @param float[] $rgb_array An array representing an RGB color.
 *
 * @link https://en.wikipedia.org/wiki/Relative_luminance
 *
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
 *
 * @return bool True if the theme's background has a "dark" luminance.
 */
function is_dark_theme( $rgb_array ) {
	return get_relative_luminance( $rgb_array ) <= 0.6;
}

/**
 * Convert an RGB array to hexadecimal representation.
 *
 * @param float[] $rgb_array The RGB array to convert.
 *
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
 * Get the default selected line background color.
 *
 * In a dark theme, the background color is decided by adding a 15% tint to the
 * color.
 *
 * In a light theme, a default light blue is used.
 *
 * @param string $theme_name The theme name to get a color for.
 *
 * @return string A hexadecimal value.
 */
function get_default_line_bg_color( $theme_name ) {
	require_highlight_php_functions();

	$theme_rgb = \HighlightUtilities\getThemeBackgroundColor( $theme_name );

	if ( is_dark_theme( $theme_rgb ) ) {
		return get_hex_from_rgb(
			add_tint_to_rgb( $theme_rgb, 0.15 )
		);
	}

	return '#ddf6ff';
}

/**
 * Get an array of all the options tied to this plugin.
 *
 * @return array
 */
function get_options() {
	$options = \get_option( OPTION_NAME, [] );

	return array_merge(
		[
			'theme_name'             => DEFAULT_THEME,
			'selected_line_bg_color' => get_default_line_bg_color( DEFAULT_THEME ),
		],
		$options
	);
}

/**
 * Get the single, specified plugin option.
 *
 * @param string $option_name The plugin option name.
 *
 * @return mixed
 */
function get_option( $option_name ) {
	return get_options()[ $option_name ];
}

/**
 * Get path to script deps file.
 *
 * @return string Path.
 */
function get_script_deps_path() {
	return __DIR__ . '/build/index.deps.json';
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

	add_action( 'wp_default_styles', __NAMESPACE__ . '\register_styles' );
	add_action( 'enqueue_block_editor_assets', __NAMESPACE__ . '\enqueue_editor_assets' );
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
	$script_handle = 'syntax-highlighting-code-block-scripts';
	$script_path   = '/build/index.js';
	$style_handle  = 'syntax-highlighting-code-block-styles';
	$style_path    = '/editor-styles.css';
	$script_deps   = json_decode( file_get_contents( get_script_deps_path() ), false ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
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
		$script_deps,
		DEVELOPMENT_MODE ? filemtime( plugin_dir_path( __FILE__ ) . $script_path ) : PLUGIN_VERSION,
		$in_footer
	);

	wp_set_script_translations( $script_handle, 'syntax-highlighting-code-block' );
}

/**
 * Register assets for the frontend.
 *
 * Asset(s) will only be enqueued if needed.
 *
 * @param WP_Styles $styles Styles.
 */
function register_styles( WP_Styles $styles ) {
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
		$style = get_option( 'theme_name' );
	}

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
	wp_enqueue_style( FRONTEND_STYLE_HANDLE );

	// Include line-number styles if requesting to show lines.
	if ( $attributes['showLines'] ) {
		$after_styles = wp_styles()->get_data( FRONTEND_STYLE_HANDLE, 'after' );
		if ( ! is_array( $after_styles ) ) {
			$after_styles = '';
		} else {
			$after_styles = implode( '', $after_styles );
		}

		// Only include line-number styles if not already included.
		if ( false === strpos( $after_styles, '.hljs.line-numbers' ) ) {
			wp_add_inline_style(
				FRONTEND_STYLE_HANDLE,
				file_get_contents( __DIR__ . '/line-numbers.css' ) // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			);

			$line_color = get_option( 'selected_line_bg_color' );
			$inline_css = ".hljs .loc.highlighted { background-color: $line_color; }";

			wp_add_inline_style( FRONTEND_STYLE_HANDLE, $inline_css );
		}
	}

	$inject_classes = function( $start_tags, $language, $show_lines, $has_selected_lines ) {
		$added_classes = "hljs language-$language";

		if ( $show_lines ) {
			$added_classes .= ' line-numbers';
		}

		if ( $has_selected_lines ) {
			$added_classes .= ' selected-lines';
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

	/**
	 * Filters the list of languages that are used for auto-detection.
	 *
	 * @param string[] $auto_detect_language Auto-detect languages.
	 */
	$auto_detect_languages = apply_filters( 'syntax_highlighting_code_block_auto_detect_languages', [] );

	$transient_key = 'syntax-highlighting-code-block-' . md5( $attributes['showLines'] . $attributes['language'] . implode( '', $auto_detect_languages ) . $matches['code'] ) . '-v' . PLUGIN_VERSION;
	$highlighted   = get_transient( $transient_key );

	if ( ! DEVELOPMENT_MODE && $highlighted && isset( $highlighted['code'] ) ) {
		if ( isset( $highlighted['language'] ) ) {
			$matches['before'] = $inject_classes( $matches['before'], $highlighted['language'], $highlighted['show_lines'], $highlighted['has_selected_lines'] );
		}
		return $matches['before'] . $highlighted['code'] . $after;
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

		$highlighter = new \Highlight\Highlighter();
		if ( ! empty( $auto_detect_languages ) ) {
			$highlighter->setAutodetectLanguages( $auto_detect_languages );
		}

		$language = $attributes['language'];
		$code     = html_entity_decode( $matches['code'], ENT_QUOTES );

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

		$code               = $r->value;
		$language           = $r->language;
		$show_lines         = $attributes['showLines'];
		$has_selected_lines = ! empty( $attributes['selectedLines'] );

		if ( $show_lines || $has_selected_lines ) {
			require_highlight_php_functions();

			$selected_lines = parse_selected_lines( $attributes['selectedLines'] );
			$lines          = \HighlightUtilities\splitCodeIntoArray( $code );
			$code           = '';

			// We need to wrap the line of code twice in order to let out `white-space: pre` CSS setting to be respected
			// by our `table-row`.
			foreach ( $lines as $i => $line ) {
				$class_name = 'loc';

				if ( in_array( $i, $selected_lines, true ) ) {
					$class_name .= ' highlighted';
				}

				$code .= sprintf( '<div class="%s"><span>%s</span></div>%s', $class_name, $line, PHP_EOL );
			}
		}

		$highlighted = compact( 'code', 'language', 'show_lines', 'has_selected_lines' );

		set_transient( $transient_key, compact( 'code', 'language', 'show_lines', 'has_selected_lines' ), MONTH_IN_SECONDS );

		$matches['before'] = $inject_classes(
			$matches['before'],
			$highlighted['language'],
			$highlighted['show_lines'],
			$highlighted['has_selected_lines']
		);

		return $matches['before'] . $code . $after;
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
 * Parse the selected line syntax from the front-end and return an array of selected lines.
 *
 * @param string $selected_lines The selected line syntax.
 *
 * @return int[]
 */
function parse_selected_lines( $selected_lines ) {
	$highlighted_lines = [];

	if ( ! $selected_lines || empty( trim( $selected_lines ) ) ) {
		return $highlighted_lines;
	}

	$ranges = explode( ',', preg_replace( '/\s/', '', $selected_lines ) );

	foreach ( $ranges as $chunk ) {
		if ( strpos( $chunk, '-' ) !== false ) {
			$range = explode( '-', $chunk );

			if ( count( $range ) === 2 ) {
				for ( $i = (int) $range[0]; $i < (int) $range[1]; $i++ ) {
					$highlighted_lines[] = $i - 1;
				}
			}
		} else {
			$highlighted_lines[] = (int) $chunk - 1;
		}
	}

	return $highlighted_lines;
}

/**
 * Validate the given stylesheet name against available stylesheets.
 *
 * @param WP_Error $validity Validator object.
 * @param string   $input    Incoming theme name.
 *
 * @return mixed
 */
function validate_theme_name( $validity, $input ) {
	require_highlight_php_functions();

	$themes = \HighlightUtilities\getAvailableStyleSheets();

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
	if ( has_filter( BLOCK_STYLE_FILTER ) ) {
		return;
	}

	require_highlight_php_functions();

	$themes = \HighlightUtilities\getAvailableStyleSheets();
	sort( $themes );
	$choices = array_combine( $themes, $themes );

	$wp_customize->add_setting(
		'syntax_highlighting[theme_name]',
		[
			'type'              => 'option',
			'default'           => DEFAULT_THEME,
			'validate_callback' => __NAMESPACE__ . '\validate_theme_name',
		]
	);
	$wp_customize->add_control(
		'syntax_highlighting[theme_name]',
		[
			'type'        => 'select',
			'section'     => 'colors',
			'label'       => __( 'Syntax Highlighting Theme', 'syntax-highlighting-code-block' ),
			'description' => __( 'Preview the theme by navigating to a page with a code block to see the different themes in action.', 'syntax-highlighting-code-block' ),
			'choices'     => $choices,
		]
	);

	$wp_customize->add_setting(
		'syntax_highlighting[selected_line_bg_color]',
		[
			'type'              => 'option',
			'default'           => get_default_line_bg_color( get_option( 'theme_name' ) ),
			'sanitize_callback' => 'sanitize_hex_color',
		]
	);
	$wp_customize->add_control(
		new \WP_Customize_Color_Control(
			$wp_customize,
			'syntax_highlighting[selected_line_bg_color]',
			[
				'section'     => 'colors',
				'settings'    => 'syntax_highlighting[selected_line_bg_color]',
				'label'       => __( 'Highlighted Line Color', 'syntax-highlighting-code-block' ),
				'description' => __( 'The background color of a selected line.', 'syntax-highlighting-code-block' ),
			]
		)
	);
}
add_action( 'customize_register', __NAMESPACE__ . '\customize_register' );
