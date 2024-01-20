/* global syntaxHighlightingCodeBlockLanguageNames */

/**
 * WordPress dependencies
 */

import { Fragment, useRef, useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
	RichText,
	useBlockProps,
	InspectorControls,
} from '@wordpress/block-editor';
import {
	SelectControl,
	TextControl,
	CheckboxControl,
	PanelBody,
	PanelRow,
} from '@wordpress/components';
import { createBlock, getDefaultBlockName } from '@wordpress/blocks';

/**
 * External dependencies
 */
import { sortBy } from 'lodash';

const languageNames = syntaxHighlightingCodeBlockLanguageNames;

const HighlightableTextArea = (props_) => {
	const { highlightedLines, ...props } = props_;
	const textAreaRef = useRef();
	const [styles, setStyles] = useState({});

	useEffect(() => {
		if (textAreaRef.current !== null) {
			const element = textAreaRef.current;
			const computedStyles = window.getComputedStyle(element);

			setStyles({
				fontFamily: computedStyles.getPropertyValue('font-family'),
				fontSize: computedStyles.getPropertyValue('font-size'),
				overflow: 'hidden', // Prevent doubled-scrollbars from appearing.
				overflowWrap: computedStyles.getPropertyValue('overflow-wrap'),
				resize: computedStyles.getPropertyValue('resize'),
			});
		}
	}, [props.style]);

	return (
		<Fragment>
			<RichText ref={textAreaRef} {...props} />
			<div
				aria-hidden={true}
				className="code-block-overlay"
				style={styles}
			>
				{(props.value || '').split(/\n|<br>/i).map((v, i) => {
					let cName = 'loc';

					if (highlightedLines.has(i)) {
						cName += ' highlighted';
					}

					return (
						<span
							key={i}
							className={cName}
							dangerouslySetInnerHTML={{
								__html: v || ' ',
							}}
						/>
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

export default function CodeEdit({
	attributes,
	setAttributes,
	onRemove,
	insertBlocksAfter,
	mergeBlocks,
}) {
	const blockProps = useBlockProps();

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

	const richTextProps = {
		// These RichText props must mirror core <https://github.com/WordPress/gutenberg/blob/e95bb8c9530bbdef1db623eca11b80bd73493197/packages/block-library/src/code/edit.js#L19-L31>.
		...{
			tagName: 'code',
			identifier: 'content',
			value: attributes.content,
			onChange: (content) => setAttributes({ content }),
			onRemove,
			onMerge: mergeBlocks,
			placeholder: __('Write code…'),
			'aria-label': __('Code'),
			preserveWhiteSpace: true,
			__unstablePastePlainText: true, // See <https://github.com/WordPress/gutenberg/pull/27236>.
			__unstableOnSplitAtDoubleLineEnd: () => {
				insertBlocksAfter(createBlock(getDefaultBlockName()));
			},
		},

		// Additional props unique to HighlightableTextArea.
		...{
			highlightedLines: parseHighlightedLines(
				attributes.highlightedLines
			),
			className: [
				'shcb-textedit',
				attributes.wrapLines ? 'shcb-textedit-wrap-lines' : '',
			].join(' '),

			// Copy the styles to ensure that the code-block-overlay is updated when the font size is changed.
			style: blockProps.style,
		},
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
			{/* Keep in sync with https://github.com/WordPress/gutenberg/blob/e95bb8c9530bbdef1db623eca11b80bd73493197/packages/block-library/src/code/edit.js#L17 */}
			<pre {...blockProps}>
				<HighlightableTextArea {...richTextProps} />
			</pre>
		</Fragment>
	);
}
