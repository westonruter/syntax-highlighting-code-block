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
import { hasBlockSupport } from '@wordpress/blocks';
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
const extendCodeBlockWithSyntaxHighlighting = ( settings ) => {
	if ( 'core/code' !== settings.name ) {
		return settings;
	}

	const useLightBlockWrapper =
		hasBlockSupport( settings, 'core/code', 'lightBlockWrapper', false ) &&
		BlockEditor.__experimentalBlock &&
		BlockEditor.__experimentalBlock.pre;

	const HighlightablePlainText = ( props_ ) => {
		const { highlightedLines, ...props } = props_;
		const plainTextRef = createRef();
		const [ styles, setStyles ] = useState( {} );

		useEffect( () => {
			if ( plainTextRef.current !== null ) {
				let element = plainTextRef.current;

				// In Gutenberg 7.8 and below, the DOM element was stored in a property with the name of the node type.
				// In 7.9+, the DOM element is now stored in `current`. This block is here for backward-compatibility
				// with older Gutenberg versions.
				if ( element.hasOwnProperty( 'textarea' ) ) {
					element = plainTextRef.current.textarea;
				}

				const computedStyles = window.getComputedStyle( element );

				setStyles( {
					fontFamily: computedStyles.getPropertyValue(
						'font-family'
					),
					fontSize: computedStyles.getPropertyValue( 'font-size' ),
					overflow: computedStyles.getPropertyValue( 'overflow' ),
					overflowWrap: computedStyles.getPropertyValue(
						'overflow-wrap'
					),
					resize: computedStyles.getPropertyValue( 'resize' ),
				} );
			}
		}, [] );

		return (
			<Fragment>
				<PlainText ref={ plainTextRef } { ...props } />
				<div
					aria-hidden={ true }
					className="code-block-overlay"
					style={ styles }
				>
					{ props.value.split( /\n/ ).map( ( v, i ) => {
						let cName = 'loc';

						if ( highlightedLines.has( i ) ) {
							cName += ' highlighted';
						}

						return (
							<span key={ i } className={ cName }>
								{ v || ' ' }
							</span>
						);
					} ) }
				</div>
			</Fragment>
		);
	};

	const parseSelectedLines = ( selectedLines ) => {
		const highlightedLines = new Set();

		if ( ! selectedLines || selectedLines.trim().length === 0 ) {
			return highlightedLines;
		}

		let chunk;
		const ranges = selectedLines.replace( /\s/, '' ).split( ',' );

		for ( chunk of ranges ) {
			if ( chunk.indexOf( '-' ) >= 0 ) {
				let i;
				const range = chunk.split( '-' );

				if ( range.length === 2 ) {
					for ( i = +range[ 0 ]; i <= +range[ 1 ]; ++i ) {
						highlightedLines.add( i - 1 );
					}
				}
			} else {
				highlightedLines.add( +chunk - 1 );
			}
		}

		return highlightedLines;
	};

	return {
		...settings,

		attributes: {
			...settings.attributes,
			language: {
				type: 'string',
			},
			selectedLines: {
				type: 'string',
			},
			showLines: {
				type: 'boolean',
			},
			wrapLines: {
				type: 'boolean',
			},
		},

		edit( { attributes, setAttributes, className } ) {
			const updateLanguage = ( language ) => {
				setAttributes( { language } );
			};

			const updateSelectedLines = ( selectedLines ) => {
				setAttributes( { selectedLines } );
			};

			const updateShowLines = ( showLines ) => {
				setAttributes( { showLines } );
			};

			const updateWrapLines = ( wrapLines ) => {
				setAttributes( { wrapLines } );
			};

			const sortedLanguageNames = sortBy(
				Object.entries(
					languagesNames
				).map( ( [ value, label ] ) => ( { label, value } ) ),
				( languageOption ) => languageOption.label.toLowerCase()
			);

			const plainTextProps = {
				value: attributes.content || '',
				highlightedLines: parseSelectedLines(
					attributes.selectedLines
				),
				onChange: ( content ) => setAttributes( { content } ),
				placeholder: __( 'Write code…' ),
				'aria-label': __( 'Code' ),
			};

			return (
				<Fragment>
					<InspectorControls key="controls">
						<PanelBody
							title={ __( 'Syntax Highlighting' ) }
							initialOpen={ true }
						>
							<PanelRow>
								<SelectControl
									label={ __( 'Language' ) }
									value={ attributes.language }
									options={ [
										{
											label: __( 'Auto-detect' ),
											value: '',
										},
										...sortedLanguageNames,
									] }
									onChange={ updateLanguage }
								/>
							</PanelRow>
							<PanelRow>
								<TextControl
									label={ __( 'Highlighted Lines' ) }
									value={ attributes.selectedLines }
									onChange={ updateSelectedLines }
									help={ __( 'Supported format: 1, 3-5' ) }
								/>
							</PanelRow>
							<PanelRow>
								<CheckboxControl
									label={ __( 'Show Line Numbers' ) }
									checked={ attributes.showLines }
									onChange={ updateShowLines }
								/>
							</PanelRow>
							<PanelRow>
								<CheckboxControl
									label={ __( 'Wrap Lines' ) }
									checked={ attributes.wrapLines }
									onChange={ updateWrapLines }
								/>
							</PanelRow>
						</PanelBody>
					</InspectorControls>
					{ useLightBlockWrapper ? (
						// This must be kept in sync with <https://github.com/WordPress/gutenberg/blob/master/packages/block-library/src/code/edit.js>.
						<BlockEditor.__experimentalBlock.pre>
							<HighlightablePlainText
								{ ...plainTextProps }
								__experimentalVersion={ 2 }
								tagName="code"
							/>
						</BlockEditor.__experimentalBlock.pre>
					) : (
						<div key="editor-wrapper" className={ className }>
							<HighlightablePlainText { ...plainTextProps } />
						</div>
					) }
				</Fragment>
			);
		},

		save( { attributes } ) {
			return (
				<pre>
					<code>{ attributes.content }</code>
				</pre>
			);
		},

		// Automatically convert core code blocks to this new extended code block.
		deprecated: [
			...( settings.deprecated || [] ),
			{
				attributes: {
					...settings.attributes,
					language: {
						type: 'string',
					},
				},

				save( { attributes } ) {
					const className = attributes.language
						? 'language-' + attributes.language
						: '';
					return (
						<pre>
							<code
								lang={ attributes.language }
								className={ className }
							>
								{ attributes.content }
							</code>
						</pre>
					);
				},
			},
		],
	};
};

addFilter(
	'blocks.registerBlockType',
	'westonruter/syntax-highlighting-code-block',
	extendCodeBlockWithSyntaxHighlighting
);
