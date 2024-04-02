<?php
/**
 * Functions file.
 *
 * TODO: Refactor into classes.
 *
 * @package Syntax_Highlighting_Code_Block
 */

namespace Syntax_Highlighting_Code_Block;

use Exception;
use WP_Block_Type;
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

/**
 * Boot the plugin.
 *
 * @noinspection PhpUnused -- See https://youtrack.jetbrains.com/issue/WI-22217/Extend-possible-linking-between-function-and-callback-using-different-constants-NAMESPACE-CLASS-and-class
 */
function boot(): void {
	add_action( 'init', __NAMESPACE__ . '\init', 100 );
	add_action( 'customize_register', __NAMESPACE__ . '\customize_register', 100 );
	add_action( 'rest_api_init', __NAMESPACE__ . '\register_rest_endpoint' );
	add_action( 'enqueue_block_assets', __NAMESPACE__ . '\register_styles' );
}

/**
 * Add a tint to an RGB color and make it lighter.
 *
 * @param float[] $rgb_array An array representing an RGB color.
 * @param float   $tint      How much of a tint to apply; a number between 0 and 1.
 * @return float[] The new color as an RGB array.
 */
function add_tint_to_rgb( array $rgb_array, float $tint ): array {
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
 *     The closer to 100, the higher the luminance is; i.e. the lighter it is.
 */
function get_relative_luminance( array $rgb_array ): float {
	return 0.2126 * ( $rgb_array['r'] / 255 ) +
		0.7152 * ( $rgb_array['g'] / 255 ) +
		0.0722 * ( $rgb_array['b'] / 255 );
}

/**
 * Check whether a given RGB array is considered a "dark theme."
 *
 * @param float[] $rgb_array The RGB array to test.
 * @return bool True if the theme's background has a "dark" luminance.
 */
function is_dark_theme( array $rgb_array ): bool {
	return get_relative_luminance( $rgb_array ) <= 0.6;
}

/**
 * Convert an RGB array to hexadecimal representation.
 *
 * @param float[] $rgb_array The RGB array to convert.
 * @return string A hexadecimal representation.
 */
function get_hex_from_rgb( array $rgb_array ): string {
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
function get_default_line_background_color( string $theme_name ): string {
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
 * @return array{
 *     theme_name: string,
 *     highlighted_line_background_color: string
 * }
 */
function get_plugin_options(): array {
	$options = get_option( OPTION_NAME );
	if ( ! is_array( $options ) ) {
		$options = [];
	}

	if ( isset( $options['theme_name'] ) && is_string( $options['theme_name'] ) ) {
		$theme_name = $options['theme_name'];
	} else {
		$theme_name = DEFAULT_THEME;
	}

	if ( isset( $options['highlighted_line_background_color'] ) && is_string( $options['highlighted_line_background_color'] ) ) {
		$highlighted_line_background_color = $options['highlighted_line_background_color'];
	} else {
		$highlighted_line_background_color = get_default_line_background_color( $theme_name );
	}

	return compact( 'theme_name', 'highlighted_line_background_color' );
}

/**
 * Get the single, specified plugin option.
 *
 * @param string $option_name The plugin option name.
 * @return string|null
 */
function get_plugin_option( string $option_name ): ?string {
	$options = get_plugin_options();
	if ( array_key_exists( $option_name, $options ) ) {
		return $options[ $option_name ];
	}
	return null;
}

/**
 * Require the highlight.php functions file.
 */
function require_highlight_php_functions(): void {
	require_once PLUGIN_DIR . '/' . get_highlight_php_vendor_path() . '/HighlightUtilities/functions.php';
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
function init(): void {
	if ( ! function_exists( 'register_block_type' ) ) {
		return;
	}

	if ( DEVELOPMENT_MODE && ! file_exists( PLUGIN_DIR . '/build/index.asset.php' ) ) {
		add_action( 'admin_notices', __NAMESPACE__ . '\print_build_required_admin_notice' );
		return;
	}

	$registry = WP_Block_Type_Registry::get_instance();

	$block = $registry->get_registered( BLOCK_NAME );
	if ( $block instanceof WP_Block_Type ) {
		$block->render_callback = __NAMESPACE__ . '\render_block';
		$block->attributes      = array_merge( $block->attributes ?? [], ATTRIBUTE_SCHEMA );
		$block->style_handles   = array_merge( $block->style_handles, STYLE_HANDLES );
	} else {
		$block = register_block_type(
			BLOCK_NAME,
			[
				'render_callback' => __NAMESPACE__ . '\render_block',
				'attributes'      => ATTRIBUTE_SCHEMA,
				'style_handles'   => STYLE_HANDLES,
			]
		);
	}

	if ( $block instanceof WP_Block_Type ) {
		register_editor_assets( $block );
		$block->editor_script_handles[] = EDITOR_SCRIPT_HANDLE;
		$block->editor_style_handles[]  = EDITOR_STYLE_HANDLE;
	}
}

/**
 * Print admin notice when plugin installed from source but no build being performed.
 *
 * @noinspection PhpUnused -- See https://youtrack.jetbrains.com/issue/WI-22217/Extend-possible-linking-between-function-and-callback-using-different-constants-NAMESPACE-CLASS-and-class
 */
function print_build_required_admin_notice(): void {
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
 * Register assets for editor.
 *
 * @param WP_Block_Type $block Block.
 */
function register_editor_assets( WP_Block_Type $block ): void {
	$style_path = '/editor-styles.css';
	wp_register_style(
		EDITOR_STYLE_HANDLE,
		plugins_url( $style_path, PLUGIN_MAIN_FILE ),
		[],
		SCRIPT_DEBUG
			? (string) filemtime( plugin_dir_path( PLUGIN_MAIN_FILE ) . $style_path )
			: PLUGIN_VERSION
	);

	$script_path  = '/build/index.js';
	$script_asset = require PLUGIN_DIR . '/build/index.asset.php';
	wp_register_script(
		EDITOR_SCRIPT_HANDLE,
		plugins_url( $script_path, PLUGIN_MAIN_FILE ),
		$script_asset['dependencies'],
		$script_asset['version'],
		true
	);

	wp_set_script_translations( EDITOR_SCRIPT_HANDLE, 'syntax-highlighting-code-block' );

	$data = [
		'name'       => $block->name,
		'attributes' => $block->attributes,
		'deprecated' => [
			'selectedLines' => [
				'type'    => 'string',
				'default' => '',
			],
			'showLines'     => [
				'type'    => 'boolean',
				'default' => false,
			],
		],
	];
	wp_add_inline_script(
		EDITOR_SCRIPT_HANDLE,
		sprintf( 'const syntaxHighlightingCodeBlockType = %s;', wp_json_encode( $data ) ),
		'before'
	);

	wp_add_inline_script(
		EDITOR_SCRIPT_HANDLE,
		sprintf( 'const syntaxHighlightingCodeBlockLanguageNames = %s;', wp_json_encode( get_language_names() ) ),
		'before'
	);
}

/**
 * Get highlight theme name.
 *
 * @return string Theme name or empty string if disabled.
 */
function get_theme_name(): string {
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
		if ( ! is_string( $style ) ) {
			$style = DEFAULT_THEME;
		}
	} else {
		$style = get_plugin_options()['theme_name'];
	}
	return is_string( $style ) ? $style : '';
}

/**
 * Register styles for the frontend.
 *
 *  @noinspection PhpUnused -- See https://youtrack.jetbrains.com/issue/WI-22217/Extend-possible-linking-between-function-and-callback-using-different-constants-NAMESPACE-CLASS-and-class
 */
function register_styles(): void {
	if ( ! is_styling_enabled() || is_admin() ) { // TODO: The same styling should be used in the admin.
		return;
	}
	$styles = wp_styles();
	$theme  = get_theme_name();

	$theme_style_path = sprintf(
		'%s/styles/%s.css',
		get_highlight_php_vendor_path(),
		0 === validate_file( $theme ) ? $theme : DEFAULT_THEME
	);
	$styles->add(
		THEME_STYLE_HANDLE,
		plugins_url( $theme_style_path, PLUGIN_MAIN_FILE ),
		[],
		SCRIPT_DEBUG
			? (string) filemtime( plugin_dir_path( PLUGIN_MAIN_FILE ) . $theme_style_path )
			: PLUGIN_VERSION
	);

	// TODO: Ideally this would be minified.
	$block_style_name = 'style.css';
	$block_style_path = plugin_dir_path( PLUGIN_MAIN_FILE ) . $block_style_name;
	$styles->add(
		BLOCK_STYLE_HANDLE,
		plugins_url( $block_style_name, PLUGIN_MAIN_FILE ),
		[],
		SCRIPT_DEBUG
			? (string) filemtime( $block_style_path )
			: PLUGIN_VERSION
	);
	wp_style_add_data( BLOCK_STYLE_HANDLE, 'path', $block_style_path );

	if ( has_filter( HIGHLIGHTED_LINE_BACKGROUND_COLOR_FILTER ) ) {
		$default_line_color = get_default_line_background_color( DEFAULT_THEME );
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
		$line_color = apply_filters( HIGHLIGHTED_LINE_BACKGROUND_COLOR_FILTER, $default_line_color );
		if ( ! is_string( $line_color ) ) {
			$line_color = $default_line_color;
		}
	} else {
		$line_color = get_plugin_options()['highlighted_line_background_color'];
	}
	wp_add_inline_style(
		BLOCK_STYLE_HANDLE,
		/* language=CSS */
		".hljs > mark.shcb-loc { background-color: $line_color; }"
	);
}

/**
 * Determines whether styling is enabled.
 *
 * @return bool Styling.
 */
function is_styling_enabled(): bool {
	/**
	 * Filters whether the Syntax-highlighting Code Block's default styling is enabled.
	 *
	 * @param bool $enabled Default styling enabled.
	 */
	return (bool) apply_filters( 'syntax_highlighting_code_block_styling', true );
}

/**
 * Language names.
 *
 * @return array<string, string> Mapping slug to name.
 */
function get_language_names(): array {
	return require PLUGIN_DIR . '/language-names.php';
}

/**
 * Inject class names and styles into the
 *
 * @param string $pre_start_tag  The `<pre>` start tag.
 * @param string $code_start_tag The `<code>` start tag.
 * @param array{
 *     language: string,
 *     highlightedLines: string,
 *     showLineNumbers: bool,
 *     wrapLines: bool
 * }             $attributes     Attributes.
 * @param string $content        Content.
 * @return string Injected markup.
 */
function inject_markup( string $pre_start_tag, string $code_start_tag, array $attributes, string $content ): string {
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

	// @todo Update this to use WP_HTML_Tag_Processor.
	$code_start_tag = (string) preg_replace(
		'/(<code[^>]*\sclass=")/',
		'$1' . esc_attr( $added_classes ) . ' ',
		$code_start_tag,
		1,
		$count
	);
	if ( 0 === $count ) {
		$code_start_tag = (string) preg_replace(
			'/(?<=<code\b)/',
			sprintf( ' class="%s"', esc_attr( $added_classes ) ),
			$code_start_tag,
			1
		);
	}

	$end_tags = '</code></span>';

	// Add language label if one was detected and if we're not in a feed.
	if ( ! is_feed() && ! empty( $attributes['language'] ) ) {
		$language_names = get_language_names();
		$language_name  = $language_names[ $attributes['language'] ] ?? $attributes['language'];

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

	return $pre_start_tag . '<span>' . $code_start_tag . escape( $content ) . $end_tags;
}

/**
 * Escape content.
 *
 * In order to prevent WordPress the_content filters from rendering embeds/shortcodes, it's important
 * to re-escape the content in the same way as the editor is doing with the Code block's save function.
 * Note this does not need to escape ampersands because they will already be escaped by highlight.php.
 * Also, escaping of ampersands was removed in <https://github.com/WordPress/gutenberg/commit/f5c32f8>
 * once HTML editing of Code blocks was implemented.
 *
 * @link <https://github.com/westonruter/syntax-highlighting-code-block/issues/668>
 * @link <https://github.com/WordPress/gutenberg/blob/32b4481/packages/block-library/src/code/utils.js>
 * @link <https://github.com/WordPress/gutenberg/pull/13996>
 *
 * @param string $content Highlighted content.
 * @return string Escaped content.
 */
function escape( string $content ): string {
	// See escapeOpeningSquareBrackets: <https://github.com/WordPress/gutenberg/blob/32b4481/packages/block-library/src/code/utils.js#L19-L34>.
	$content = str_replace( '[', '&#91;', $content );

	// See escapeProtocolInIsolatedUrls: <https://github.com/WordPress/gutenberg/blob/32b4481/packages/block-library/src/code/utils.js#L36-L55>.
	return (string) preg_replace( '/^(\s*https?:)\/\/([^\s<>"]+\s*)$/m', '$1&#47;&#47;$2', $content );
}

/**
 * Get transient key.
 *
 * Returns null if key cannot be computed.
 *
 * @param string   $content               Content.
 * @param array{
 *     language: string,
 *     highlightedLines: string,
 *     showLineNumbers: bool,
 *     wrapLines: bool
 * }               $attributes            Attributes.
 * @param bool     $is_feed               Is feed.
 * @param string[] $auto_detect_languages Auto-detect languages.
 *
 * @return string|null Transient key.
 */
function get_transient_key( string $content, array $attributes, bool $is_feed, array $auto_detect_languages ): ?string {
	$hash_input = wp_json_encode(
		[
			'content'               => $content,
			'attributes'            => $attributes,
			'is_feed'               => $is_feed, // TODO: This is obsolete.
			'auto_detect_languages' => $auto_detect_languages,
			'version'               => PLUGIN_VERSION,
		]
	);
	if ( ! is_string( $hash_input ) ) {
		return null;
	}
	return 'shcb-' . md5( $hash_input );
}

/**
 * Render code block.
 *
 * @param array{
 *     language: string,
 *     highlightedLines: string,
 *     showLineNumbers: bool,
 *     wrapLines: bool,
 *     selectedLines?: string,
 *     showLines?: bool
 * }             $attributes Attributes.
 * @param string $content    Content.
 * @return string Highlighted content.
 */
function render_block( array $attributes, string $content ): string {
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
	if ( ! is_array( $auto_detect_languages ) ) {
		$auto_detect_languages = [];
	}
	$auto_detect_languages = array_filter( $auto_detect_languages, 'is_string' );

	// Use the previously-highlighted content if cached.
	$transient_key = ! DEVELOPMENT_MODE ? get_transient_key( $matches['content'], $attributes, is_feed(), $auto_detect_languages ) : null;
	$highlighted   = $transient_key ? get_transient( $transient_key ) : null;
	if (
		is_array( $highlighted )
		&&
		isset( $highlighted['content'] ) && is_string( $highlighted['content'] )
		&&
		is_array( $highlighted['attributes'] )
		&&
		isset( $highlighted['attributes']['language'] ) && is_string( $highlighted['attributes']['language'] )
		&&
		isset( $highlighted['attributes']['highlightedLines'] ) && is_string( $highlighted['attributes']['highlightedLines'] )
		&&
		isset( $highlighted['attributes']['showLineNumbers'] ) && is_bool( $highlighted['attributes']['showLineNumbers'] )
		&&
		isset( $highlighted['attributes']['wrapLines'] ) && is_bool( $highlighted['attributes']['wrapLines'] )
	) {
		return inject_markup( $matches['pre_start_tag'], $matches['code_start_tag'], $highlighted['attributes'], $highlighted['content'] );
	}

	try {
		if ( ! class_exists( '\Highlight\Autoloader' ) ) {
			require_once PLUGIN_DIR . '/' . get_highlight_php_vendor_path() . '/Highlight/Autoloader.php';
			spl_autoload_register( 'Highlight\Autoloader::load' );
		}

		$highlighter = new Highlighter();
		if ( ! empty( $auto_detect_languages ) ) {
			$highlighter->setAutodetectLanguages( $auto_detect_languages );
		}

		$language = $attributes['language'];

		// As of Gutenberg 17.1, line breaks in Code blocks are serialized as <br> tags whereas previously they were newlines.
		$content = str_replace( '<br>', "\n", $matches['content'] );

		// Note that the decoding here is reversed later in the escape() function.
		// @todo Now that Code blocks may have markup (e.g. bolding, italics, and hyperlinks), these need to be removed and then restored after highlighting is completed.
		$content = html_entity_decode( $content, ENT_QUOTES );

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
			$lines             = split_code_into_array( $content );
			$content           = '';

			// We need to wrap the line of code twice in order to let out `white-space: pre` CSS setting to be respected
			// by our `table-row`.
			foreach ( $lines as $i => $line ) {
				$tag_name = in_array( $i, $highlighted_lines, true ) ? 'mark' : 'span';
				$content .= "<$tag_name class='shcb-loc'><span>$line\n</span></$tag_name>";
			}
		}

		if ( $transient_key ) {
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
 * Split code into an array.
 *
 * @param string $code Code to split.
 * @return string[] Lines.
 * @throws Exception If an error occurred in splitting up by lines.
 */
function split_code_into_array( string $code ): array {
	$lines = splitCodeIntoArray( $code );
	if ( ! is_array( $lines ) ) {
		throw new Exception( 'Unable to split code into array.' );
	}
	return $lines;
}

/**
 * Parse the highlighted line syntax from the front-end and return an array of highlighted line numbers.
 *
 * @param string $highlighted_lines The highlighted line syntax.
 * @return int[]
 */
function parse_highlighted_lines( string $highlighted_lines ): array {
	$highlighted_line_numbers = [];

	if ( ! $highlighted_lines || empty( trim( $highlighted_lines ) ) ) {
		return $highlighted_line_numbers;
	}

	$ranges = explode( ',', (string) preg_replace( '/\s/', '', $highlighted_lines ) );

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
 * @return WP_Error Amended errors.
 */
function validate_theme_name( WP_Error $validity, string $input ): WP_Error {
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
function customize_register( WP_Customize_Manager $wp_customize ): void {
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
		/**
		 * Theme name sanitized by Customizer setting callback & default
		 *
		 * @var string $theme_name
		 */
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

	if ( ! has_filter( HIGHLIGHTED_LINE_BACKGROUND_COLOR_FILTER ) && $theme_name ) {
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
					'setting'     => 'syntax_highlighting[highlighted_line_background_color]',
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

/**
 * Enqueue scripts for Customizer.
 *
 * @noinspection PhpUnused -- See https://youtrack.jetbrains.com/issue/WI-22217/Extend-possible-linking-between-function-and-callback-using-different-constants-NAMESPACE-CLASS-and-class
 */
function enqueue_customize_scripts(): void {
	$script_handle = 'syntax-highlighting-code-block-customize-controls';
	$script_path   = '/build/customize-controls.js';
	$script_asset  = require PLUGIN_DIR . '/build/customize-controls.asset.php';

	wp_enqueue_script(
		$script_handle,
		plugins_url( $script_path, PLUGIN_MAIN_FILE ),
		array_merge( [ 'customize-controls' ], $script_asset['dependencies'] ),
		$script_asset['version'],
		true
	);
}

/**
 * Register REST endpoint.
 *
 * @noinspection PhpUnused -- See https://youtrack.jetbrains.com/issue/WI-22217/Extend-possible-linking-between-function-and-callback-using-different-constants-NAMESPACE-CLASS-and-class
 */
function register_rest_endpoint(): void {
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

/**
 * Gets relative path to highlight.php library in vendor directory.
 *
 * @return string Relative path.
 */
function get_highlight_php_vendor_path(): string {
	if ( DEVELOPMENT_MODE && file_exists( PLUGIN_DIR . '/vendor/scrivo/highlight.php' ) ) {
		return 'vendor/scrivo/highlight.php';
	} else {
		return 'vendor/scrivo/highlight-php';
	}
}
