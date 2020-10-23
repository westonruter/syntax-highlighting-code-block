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
 * Escapes ampersands, shortcodes, and links.
 *
 * @param {string} content The content of a code block.
 * @return {string} The given content with some characters escaped.
 */
export function escape(content) {
	return flow(
		escapeEditableHTML,
		escapeOpeningSquareBrackets,
		escapeProtocolInIsolatedUrls
	)(content || '');
}

/**
 * Escapes ampersands, shortcodes, and links (for rich text).
 *
 * This is the version copied from Gutenberg v9.2.1.
 *
 * @see https://github.com/WordPress/gutenberg/blob/07b3ab2b4fb5e3c8d8b6e235b24cefbe779050a9/packages/block-library/src/code/utils.js
 *
 * @param {string} content The content of a code block.
 * @return {string} The given content with some characters escaped.
 */
export function escapeRichText(content) {
	return flow(
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
