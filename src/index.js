/* global syntaxHighlightingCodeBlockType */

/**
 * WordPress dependencies
 */
import { addFilter } from '@wordpress/hooks';
import { useBlockProps, RichText } from '@wordpress/block-editor';

/*
 @todo This does not work. It tries to load a 'wp-block-library/code/utils' file.
 This is due to DependencyExtractionWebpackPlugin https://developer.wordpress.org/block-editor/reference-guides/packages/packages-dependency-extraction-webpack-plugin/
 The block-library package also must not be dev package, since it will need to be bundled.
 */
import { escape } from '@wordpress/block-library/code/utils';

/**
 * Internal dependencies
 */
import edit from './edit';

/**
 * Extend code block with syntax highlighting.
 *
 * @param {Object} settings Settings.
 * @return {Object} Modified settings.
 */
const extendCodeBlockWithSyntaxHighlighting = (settings) => {
	if (syntaxHighlightingCodeBlockType.name !== settings.name) {
		return settings;
	}

	return {
		...settings,

		/*
		 * @todo Why do the attributes need to be augmented here when they have already been declared for the block type in PHP?
		 * There seems to be a race condition, as wp.blocks.getBlockType('core/code') returns the PHP-augmented data after the
		 * page loads, but at the moment this filter calls it is still undefined.
		 */
		attributes: {
			...settings.attributes,
			...syntaxHighlightingCodeBlockType.attributes, // @todo Why can't this be supplied via a blocks.getBlockAttributes filter?
		},

		edit,

		save({ attributes }) {
			// Keep in sync with <https://github.com/WordPress/gutenberg/blob/a42fd75/packages/block-library/src/code/save.js#L13-L18>.
			return (
				<pre {...useBlockProps.save()}>
					<RichText.Content
						tagName="code"
						value={escape(attributes.content)}
					/>
				</pre>
			);
		},

		deprecated: [
			...(settings.deprecated || []),
			{
				attributes: {
					...settings.attributes,
					...syntaxHighlightingCodeBlockType.deprecated,
				},
				isEligible(attributes) {
					return Object.keys(attributes).some((attribute) => {
						return syntaxHighlightingCodeBlockType.deprecated.hasOwnProperty(
							attribute
						);
					});
				},
				migrate(attributes, innerBlocks) {
					return [
						{
							...attributes,
							highlightedLines: attributes.selectedLines,
							showLineNumbers: attributes.showLines,
						},
						innerBlocks,
					];
				},
			},
		],
	};
};

addFilter(
	'blocks.registerBlockType',
	'westonruter/syntax-highlighting-code-block-type',
	extendCodeBlockWithSyntaxHighlighting
);
