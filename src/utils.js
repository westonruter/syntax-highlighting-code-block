// Copied from Gutenberg v8.3.0: https://github.com/WordPress/gutenberg/blob/aaa0b32d6d4a82d1e955569c14960f02549bae99/packages/block-library/src/code/utils.js

/**
 * External dependencies
 */
import { flow } from 'lodash';

/**
 * WordPress dependencies
 */
import { escapeEditableHTML } from '@wordpress/escape-html';

/**
 * Escapes ampersands, shortcodes, and links (for rich text).
 *
 * This is the escape() function used in the Code block starting with v9.1.0.
 *
 * @see https://github.com/WordPress/gutenberg/blob/v9.1.0/packages/block-library/src/code/utils.js
 *
 * @param {string} content The content of a code block.
 * @return {string} The given content with some characters escaped.
 */
export function escape(content) {
	return flow(
		escapeOpeningSquareBrackets,
		escapeProtocolInIsolatedUrls
	)(content || '');
}

/**
 * Escapes ampersands, shortcodes, and links.
 *
 * This is the escape() function used in the Code block in Gutenberg v6.9.0 until v9.0.0.
 *
 * @see https://github.com/WordPress/gutenberg/blob/v9.0.0/packages/block-library/src/code/utils.js
 *
 * @param {string} content The content of a code block.
 * @return {string} The given content with some characters escaped.
 */
export function escapeIncludingEditableHTML(content) {
	return flow(
		escapeEditableHTML,
		escapeOpeningSquareBrackets,
		escapeProtocolInIsolatedUrls
	)(content || '');
}

/**
 * Escapes ampersands, shortcodes, and links.
 *
 * This is the escape() function used in the Code block until Gutenberg v6.8.0.
 *
 * @see https://github.com/WordPress/gutenberg/blob/v6.8.0/packages/block-library/src/code/utils.js
 *
 * @param {string} content The content of a code block.
 * @return {string} The given content with some characters escaped.
 */
export function escapeIncludingAmpersands(content) {
	return flow(
		escapeAmpersands,
		escapeOpeningSquareBrackets,
		escapeProtocolInIsolatedUrls
	)(content || '');
}

/**
 * Returns the given content with all opening shortcode characters converted
 * into their HTML entity counterpart (i.e. [ => &#91;). For instance, a
 * shortcode like [embed] becomes &#91;embed]
 *
 * This function replicates the escaping of HTML tags, where a tag like
 * <strong> becomes &lt;strong>.
 *
 * @param {string}  content The content of a code block.
 * @return {string} The given content with its opening shortcode characters
 *                  converted into their HTML entity counterpart
 *                  (i.e. [ => &#91;)
 */
function escapeOpeningSquareBrackets(content) {
	return content.replace(/\[/g, '&#91;');
}

/**
 * Converts the first two forward slashes of any isolated URL into their HTML
 * counterparts (i.e. // => &#47;&#47;). For instance, https://youtube.com/watch?x
 * becomes https:&#47;&#47;youtube.com/watch?x.
 *
 * An isolated URL is a URL that sits in its own line, surrounded only by spacing
 * characters.
 *
 * See https://github.com/WordPress/wordpress-develop/blob/5.1.1/src/wp-includes/class-wp-embed.php#L403
 *
 * @param {string}  content The content of a code block.
 * @return {string} The given content with its ampersands converted into
 *                  their HTML entity counterpart (i.e. & => &amp;)
 */
function escapeProtocolInIsolatedUrls(content) {
	return content.replace(
		/^(\s*https?:)\/\/([^\s<>"]+\s*)$/m,
		'$1&#47;&#47;$2'
	);
}

/**
 * Returns the given content with all its ampersand characters converted
 * into their HTML entity counterpart (i.e. & => &amp;)
 *
 * This was removed from Gutenberg in v6.9.0.
 *
 * @see https://github.com/WordPress/gutenberg/blob/v6.8.0/packages/block-library/src/code/utils.js
 *
 * @param {string}  content The content of a code block.
 * @return {string} The given content with its ampersands converted into
 *                  their HTML entity counterpart (i.e. & => &amp;)
 */
function escapeAmpersands(content) {
	return content.replace(/&/g, '&amp;');
}
