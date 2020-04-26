<?php
/**
 * Plugin Name:  Syntax-highlighting Code Block (with Server-side Rendering)
 * Plugin URI:   https://github.com/westonruter/syntax-highlighting-code-block
 * Description:  Extending the Code block with syntax highlighting rendered on the server, thus being AMP-compatible and having faster frontend performance.
 * Version:      1.2-beta
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

const PLUGIN_VERSION = '1.2-beta';

const DEVELOPMENT_MODE = true; // This is automatically rewritten to false during dist build.

const OPTION_NAME = 'syntax_highlighting';

const DEFAULT_THEME = 'default';

const BLOCK_STYLE_FILTER = 'syntax_highlighting_code_block_style';

const SELECTED_LINE_BG_FILTER = 'syntax_highlighting_code_selected_line_bg';

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

	$theme_name = isset( $options['theme_name'] ) ? $options['theme_name'] : DEFAULT_THEME;
	return array_merge(
		[
			'theme_name'             => DEFAULT_THEME,
			'selected_line_bg_color' => get_default_line_bg_color( $theme_name ),
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

	if ( DEVELOPMENT_MODE && ! file_exists( __DIR__ . '/build/index.asset.php' ) ) {
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
}
add_action( 'plugins_loaded', __NAMESPACE__ . '\init' );

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
	static $added_inline_style            = false;
	static $added_highlighted_color_style = false;

	$pattern  = '(?P<before><pre.*?><code.*?>)';
	$pattern .= '(?P<content>.*)';
	$pattern .= '</code></pre>';

	if ( ! preg_match( '#^\s*' . $pattern . '\s*$#s', $content, $matches ) ) {
		return $content;
	}

	$end_tags   = '</code></div></pre>';
	$attributes = wp_parse_args(
		$attributes,
		[
			'language'      => '',
			'selectedLines' => '',
			'showLines'     => false,
			'wrapLines'     => false,
		]
	);

	if ( ! wp_style_is( FRONTEND_STYLE_HANDLE, 'registered' ) ) {
		register_styles( wp_styles() );
	}

	// Print stylesheet now that we know it will be needed. Note that the stylesheet is not being enqueued at the
	// wp_enqueue_scripts action because this could result in the stylesheet being printed when it would never be used.
	// When a stylesheet is printed in the body it has the additional benefit of not being render-blocking. When
	// a stylesheet is printed the first time, subsequent calls to wp_print_styles() will no-op.
	ob_start();
	wp_print_styles( FRONTEND_STYLE_HANDLE );
	$styles = ob_get_clean();

	// Include line-number styles if requesting to show lines.
	if ( ! $added_inline_style && ( $attributes['selectedLines'] || $attributes['showLines'] ) ) {
		$styles .= sprintf( '<style>%s</style>', file_get_contents( __DIR__ . '/style.css' ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		$added_inline_style = true;
	}

	if ( ! $added_highlighted_color_style && $attributes['selectedLines'] ) {
		if ( has_filter( SELECTED_LINE_BG_FILTER ) ) {
			/**
			 * Filters the background color of a selected line.
			 *
			 * This filter takes precedence over any settings set in the database as an option. Additionally, if this filter
			 * is provided, then a color selector will not be provided in Customizer.
			 *
			 * @param string $rgb_color An RGB hexadecimal (with the #) to be used as the background color of a selected line.
			 *
			 * @since 1.1.5
			 */
			$line_color = apply_filters( SELECTED_LINE_BG_FILTER, get_default_line_bg_color( DEFAULT_THEME ) );
		} else {
			$line_color = get_option( 'selected_line_bg_color' );
		}

		$inline_css = ".hljs > mark.shcb-loc { background-color: $line_color; }";

		$styles .= sprintf( '<style>%s</style>', $inline_css );

		$added_highlighted_color_style = true;
	}

	$inject_classes = function( $start_tags, $attributes ) {
		$added_classes = 'hljs';

		if ( $attributes['language'] ) {
			$added_classes .= " language-{$attributes['language']}";
		}

		if ( $attributes['showLines'] || $attributes['selectedLines'] ) {
			$added_classes .= ' shcb-code-table';
		}

		if ( $attributes['showLines'] ) {
			$added_classes .= ' shcb-line-numbers';
		}

		if ( $attributes['selectedLines'] ) {
			$added_classes .= ' shcb-selected-lines';
		}

		if ( $attributes['wrapLines'] ) {
			$added_classes .= ' shcb-wrap-lines';
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

		return preg_replace( '/(<pre[^>]*>)(<code)/', '$1<div>$2', $start_tags, 1 );
	};

	/**
	 * Filters the list of languages that are used for auto-detection.
	 *
	 * @param string[] $auto_detect_language Auto-detect languages.
	 */
	$auto_detect_languages = apply_filters( 'syntax_highlighting_code_block_auto_detect_languages', [] );

	$transient_key = 'syntax-highlighted-' . md5( wp_json_encode( $attributes ) . implode( '', $auto_detect_languages ) . $matches['content'] . PLUGIN_VERSION );
	$highlighted   = get_transient( $transient_key );

	if ( ! DEVELOPMENT_MODE && $highlighted && isset( $highlighted['content'] ) ) {
		return $inject_classes( $matches['before'], $highlighted['attributes'] ) . $highlighted['content'] . $end_tags;
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
		if ( $attributes['showLines'] || $attributes['selectedLines'] ) {
			require_highlight_php_functions();

			$selected_lines = parse_selected_lines( $attributes['selectedLines'] );
			$lines          = \HighlightUtilities\splitCodeIntoArray( $content );
			$content        = '';

			// We need to wrap the line of code twice in order to let out `white-space: pre` CSS setting to be respected
			// by our `table-row`.
			foreach ( $lines as $i => $line ) {
				$tag_name = in_array( $i, $selected_lines, true ) ? 'mark' : 'span';
				$content .= "<$tag_name class='shcb-loc'><span>$line\n</span></$tag_name>";
			}
		}

		set_transient( $transient_key, compact( 'content', 'attributes' ), MONTH_IN_SECONDS );

		$matches['before'] = $inject_classes(
			$matches['before'],
			$attributes
		);

		return $styles . $matches['before'] . $content . $end_tags;
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
				for ( $i = (int) $range[0]; $i <= (int) $range[1]; $i++ ) {
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
	if ( has_filter( BLOCK_STYLE_FILTER ) && has_filter( SELECTED_LINE_BG_FILTER ) ) {
		return;
	}

	require_highlight_php_functions();

	if ( ! has_filter( BLOCK_STYLE_FILTER ) ) {
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
	}

	if ( ! has_filter( SELECTED_LINE_BG_FILTER ) ) {
		$wp_customize->add_setting(
			'syntax_highlighting[selected_line_bg_color]',
			[
				'type'              => 'option',
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
}
add_action( 'customize_register', __NAMESPACE__ . '\customize_register' );

/**
 * Override the post value for the selected line background color when the theme has been selected.
 *
 * This is an unfortunate workaround for the Customizer not respecting dynamic updates to the default setting value.
 *
 * @todo What's missing is dynamically changing the default value of the selected_line_bg_color control based on the selected theme.
 *
 * @param \WP_Customize_Manager $wp_customize Customize manager.
 */
function override_selected_line_bg_color_post_value( \WP_Customize_Manager $wp_customize ) {
	$selected_line_bg_color_setting = $wp_customize->get_setting( 'syntax_highlighting[selected_line_bg_color]' );
	if ( $selected_line_bg_color_setting && ! $selected_line_bg_color_setting->post_value() ) {
		$selected_line_bg_color_setting->default = get_default_line_bg_color( get_option( 'theme_name' ) ); // This has no effect.
		$wp_customize->set_post_value( $selected_line_bg_color_setting->id, $selected_line_bg_color_setting->default );
	}
}
add_action( 'customize_preview_init', __NAMESPACE__ . '\override_selected_line_bg_color_post_value' );
