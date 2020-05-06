/* global syntaxHighlightingCodeBlockType */

/**
 * External dependencies
 */
import { sortBy } from 'lodash';

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { addFilter } from '@wordpress/hooks';
import { PlainText, InspectorControls } from '@wordpress/editor';
import {
	SelectControl,
	TextControl,
	CheckboxControl,
	PanelBody,
	PanelRow,
} from '@wordpress/components';
import { Fragment, createRef, useEffect, useState } from '@wordpress/element';
import * as BlockEditor from '@wordpress/block-editor';

/**
 * Internal dependencies
 */
import languagesNames from './language-names';

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

	const useLightBlockWrapper =
		settings.supports &&
		settings.supports.lightBlockWrapper &&
		BlockEditor.__experimentalBlock &&
		BlockEditor.__experimentalBlock.pre;

	const HighlightablePlainText = (props_) => {
		const { highlightedLines, ...props } = props_;
		const plainTextRef = createRef();
		const [styles, setStyles] = useState({});

		useEffect(() => {
			if (plainTextRef.current !== null) {
				let element = plainTextRef.current;

				// In Gutenberg 7.8 and below, the DOM element was stored in a property with the name of the node type.
				// In 7.9+, the DOM element is now stored in `current`. This block is here for backward-compatibility
				// with older Gutenberg versions.
				if (element.hasOwnProperty('textarea')) {
					element = plainTextRef.current.textarea;
				}

				const computedStyles = window.getComputedStyle(element);

				setStyles({
					fontFamily: computedStyles.getPropertyValue('font-family'),
					fontSize: computedStyles.getPropertyValue('font-size'),
					overflow: computedStyles.getPropertyValue('overflow'),
					overflowWrap: computedStyles.getPropertyValue(
						'overflow-wrap'
					),
					resize: computedStyles.getPropertyValue('resize'),
				});
			}
		}, []);

		return (
			<Fragment>
				<PlainText ref={plainTextRef} {...props} />
				<div
					aria-hidden={true}
					className="code-block-overlay"
					style={styles}
				>
					{props.value.split(/\n/).map((v, i) => {
						let cName = 'loc';

						if (highlightedLines.has(i)) {
							cName += ' highlighted';
						}

						return (
							<span key={i} className={cName}>
								{v || ' '}
							</span>
						);
					})}
				</div>
			</Fragment>
		);
	};

	const parseSelectedLines = (highlightedLines) => {
		const highlightedLinesSet = new Set();

		if (!highlightedLines || highlightedLines.trim().length === 0) {
			return highlightedLinesSet;
		}

		let chunk;
		const ranges = highlightedLines.replace(/\s/, '').split(',');

		for (chunk of ranges) {
			if (chunk.indexOf('-') >= 0) {
				let i;
				const range = chunk.split('-');

				if (range.length === 2) {
					for (i = +range[0]; i <= +range[1]; ++i) {
						highlightedLinesSet.add(i - 1);
					}
				}
			} else {
				highlightedLinesSet.add(+chunk - 1);
			}
		}

		return highlightedLinesSet;
	};

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

		edit({ attributes, setAttributes, className }) {
			const updateLanguage = (language) => {
				setAttributes({ language });
			};

			const updateHighlightedLines = (highlightedLines) => {
				setAttributes({ highlightedLines });
			};

			const updateShowLineNumbers = (showLineNumbers) => {
				setAttributes({ showLineNumbers });
			};

			const updateWrapLines = (wrapLines) => {
				setAttributes({ wrapLines });
			};

			const sortedLanguageNames = sortBy(
				Object.entries(languagesNames).map(([value, label]) => ({
					label,
					value,
				})),
				(languageOption) => languageOption.label.toLowerCase()
			);

			const plainTextProps = {
				value: attributes.content || '',
				highlightedLines: parseSelectedLines(
					attributes.highlightedLines
				),
				onChange: (content) => setAttributes({ content }),
				placeholder: __('Write code…'),
				'aria-label': __('Code'),
				className: [
					'shcb-plain-text',
					attributes.wrapLines ? 'shcb-plain-text-wrap-lines' : '',
				].join(' '),
			};

			return (
				<Fragment>
					<InspectorControls key="controls">
						<PanelBody
							title={__(
								'Syntax Highlighting',
								'syntax-highlighting-code-block'
							)}
							initialOpen={true}
						>
							<PanelRow>
								<SelectControl
									label={__(
										'Language',
										'syntax-highlighting-code-block'
									)}
									value={attributes.language}
									options={[
										{
											label: __(
												'Auto-detect',
												'syntax-highlighting-code-block'
											),
											value: '',
										},
										...sortedLanguageNames,
									]}
									onChange={updateLanguage}
								/>
							</PanelRow>
							<PanelRow>
								<TextControl
									label={__(
										'Highlighted Lines',
										'syntax-highlighting-code-block'
									)}
									value={attributes.highlightedLines}
									onChange={updateHighlightedLines}
									help={__(
										'Supported format: 1, 3-5',
										'syntax-highlighting-code-block'
									)}
								/>
							</PanelRow>
							<PanelRow>
								<CheckboxControl
									label={__(
										'Show Line Numbers',
										'syntax-highlighting-code-block'
									)}
									checked={attributes.showLineNumbers}
									onChange={updateShowLineNumbers}
								/>
							</PanelRow>
							<PanelRow>
								<CheckboxControl
									label={__(
										'Wrap Lines',
										'syntax-highlighting-code-block'
									)}
									checked={attributes.wrapLines}
									onChange={updateWrapLines}
								/>
							</PanelRow>
						</PanelBody>
					</InspectorControls>
					{useLightBlockWrapper ? (
						// This must be kept in sync with <https://github.com/WordPress/gutenberg/blob/master/packages/block-library/src/code/edit.js>.
						<BlockEditor.__experimentalBlock.pre>
							<HighlightablePlainText
								{...plainTextProps}
								__experimentalVersion={2}
								tagName="code"
							/>
						</BlockEditor.__experimentalBlock.pre>
					) : (
						<div key="editor-wrapper" className={className}>
							<HighlightablePlainText {...plainTextProps} />
						</div>
					)}
				</Fragment>
			);
		},

		save({ attributes }) {
			return (
				<pre>
					<code>{attributes.content}</code>
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
