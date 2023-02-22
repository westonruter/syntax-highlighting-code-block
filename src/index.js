/* global syntaxHighlightingCodeBlockType */

/**
 * WordPress dependencies
 */
import { addFilter } from '@wordpress/hooks';

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
