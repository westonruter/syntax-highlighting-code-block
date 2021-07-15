/* global syntaxHighlightingCodeBlockType, syntaxHighlightingCodeBlockLanguageNames */

/**
 * External dependencies
 */
import { sortBy } from 'lodash';

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { addFilter } from '@wordpress/hooks';
import {
	SelectControl,
	TextControl,
	CheckboxControl,
	PanelBody,
	PanelRow,
} from '@wordpress/components';
import { Fragment, useRef, useEffect, useState } from '@wordpress/element';
import {
	__experimentalBlock as ExperimentalBlock, /* eslint-disable-line -- WP 5.5 */
	useBlockProps, // GB 9.2, WP 5.6
	PlainText,
	RichText,
	InspectorControls,
} from '@wordpress/block-editor';
import { escapeEditableHTML } from '@wordpress/escape-html';

/**
 * Internal dependencies
 */
import {
	escape,
	escapeIncludingEditableHTML,
	escapeIncludingAmpersands,
} from './utils';

const languageNames = syntaxHighlightingCodeBlockLanguageNames;

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

	const HighlightableTextArea = (props_) => {
		const { highlightedLines, ...props } = props_;
		const textAreaRef = useRef();
		const [styles, setStyles] = useState({});

		useEffect(() => {
			if (textAreaRef.current !== null) {
				let element = textAreaRef.current;

				// In Gutenberg 7.8 and below, the DOM element was stored in a property with the name of the node type.
				// In 7.9+, the DOM element is now stored in `current`. This block is here for backward-compatibility
				// with older Gutenberg versions.
				if (element.hasOwnProperty('textarea')) {
					element = textAreaRef.current.textarea;
				}

				const computedStyles = window.getComputedStyle(element);

				setStyles({
					fontFamily: computedStyles.getPropertyValue('font-family'),
					fontSize: computedStyles.getPropertyValue('font-size'),
					overflow: 'hidden', // Prevent doubled-scrollbars from appearing.
					overflowWrap:
						computedStyles.getPropertyValue('overflow-wrap'),
					resize: computedStyles.getPropertyValue('resize'),
				});
			}
		}, [props.style]);

		const TextArea = useBlockProps ? RichText : PlainText;

		if (useBlockProps) {
			props.preserveWhiteSpace = true;
			props.__unstablePastePlainText = true; // See https://github.com/WordPress/gutenberg/pull/27236
		}

		return (
			<Fragment>
				<TextArea ref={textAreaRef} {...props} />
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

						if (useBlockProps) {
							return (
								<span
									key={i}
									className={cName}
									dangerouslySetInnerHTML={{
										__html: v || ' ',
									}}
								/>
							);
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

	/**
	 * Parse a string representation of highlighted lines into a set of each highlighted line number.
	 *
	 * @param {string} highlightedLines Highlighted lines.
	 * @return {Set<number>} Highlighted lines.
	 */
	const parseHighlightedLines = (highlightedLines) => {
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
				Object.entries(languageNames).map(([value, label]) => ({
					label,
					value,
				})),
				(languageOption) => languageOption.label.toLowerCase()
			);

			const textAreaProps = {
				value: attributes.content || '',
				highlightedLines: parseHighlightedLines(
					attributes.highlightedLines
				),
				onChange: (content) => setAttributes({ content }),
				placeholder: __('Write code…'),
				'aria-label': __('Code'),
				className: [
					'shcb-textedit',
					attributes.wrapLines ? 'shcb-textedit-wrap-lines' : '',
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
					{(() => {
						if (useBlockProps) {
							const blockProps = useBlockProps();

							// Copy the styles to ensure that the code-block-overlay is updated when the font size is changed in Gutenberg 9.5+.
							textAreaProps.style = blockProps.style;

							// Must be kept in sync with Gutenberg 9.2+: <https://github.com/WordPress/gutenberg/blob/v9.2.0/packages/block-library/src/code/edit.js>.
							return (
								<pre {...blockProps}>
									<HighlightableTextArea {...textAreaProps} />
								</pre>
							);
						} else if (
							// WP 5.5 required using a lightBlockWrapper, which was replaced by the blockProps above in WP 5.6.
							settings.supports &&
							settings.supports.lightBlockWrapper &&
							ExperimentalBlock &&
							ExperimentalBlock.pre
						) {
							// From Gutenberg 7.8...9.0: <https://github.com/WordPress/gutenberg/blob/v7.8.0/packages/block-library/src/code/edit.js>.
							return (
								<ExperimentalBlock.pre>
									<HighlightableTextArea
										{...textAreaProps}
										__experimentalVersion={2}
										tagName="code"
									/>
								</ExperimentalBlock.pre>
							);
						}

						// For WordPress versions older than 5.5 (Gutenberg<7.8): <https://github.com/WordPress/gutenberg/blob/v7.7.0/packages/block-library/src/code/edit.js>.
						return (
							<div key="editor-wrapper" className={className}>
								<HighlightableTextArea {...textAreaProps} />
							</div>
						);
					})()}
				</Fragment>
			);
		},

		save({ attributes }) {
			if (useBlockProps) {
				// From Gutenberg v9.2+ (WordPress 5.6+): <https://github.com/WordPress/gutenberg/blob/v9.2.0/packages/block-library/src/code/save.js>.
				return (
					<pre {...useBlockProps.save()}>
						<RichText.Content
							tagName="code"
							value={escape(attributes.content)}
						/>
					</pre>
				);
			} else if (escapeEditableHTML instanceof Function) {
				// From Gutenberg v6.9.0 until v9.0.0 (WordPress 5.4 & 5.5): <https://github.com/WordPress/gutenberg/blob/v9.0.0/packages/block-library/src/code/save.js>.
				return (
					<pre>
						<code>
							{escapeIncludingEditableHTML(attributes.content)}
						</code>
					</pre>
				);
			}

			// From Gutenberg v9.0 (WordPress 5.3) and before: <https://github.com/WordPress/gutenberg/blob/v9.0.0/packages/block-library/src/code/save.js>.
			return (
				<pre>
					<code>{escapeIncludingAmpersands(attributes.content)}</code>
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
