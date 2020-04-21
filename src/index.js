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
import { SelectControl, CheckboxControl, PanelBody, PanelRow } from '@wordpress/components';
import { Fragment } from '@wordpress/element';
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

	const useLightBlockWrapper = settings.supports && settings.supports.lightBlockWrapper && BlockEditor.__experimentalBlock && BlockEditor.__experimentalBlock.pre;

	return {
		...settings,

		attributes: {
			...settings.attributes,
			language: {
				type: 'string',
			},
			showLines: {
				type: 'boolean',
			},
		},

		edit( { attributes, setAttributes, className } ) {
			const updateLanguage = ( language ) => {
				setAttributes( { language } );
			};

			const updateShowLines = ( showLines ) => {
				setAttributes( { showLines } );
			};

			const sortedLanguageNames = sortBy(
				Object.entries( languagesNames ).map( ( [ value, label ] ) => ( { label, value } ) ),
				( languageOption ) => languageOption.label.toLowerCase()
			);

			const plainTextProps = {
				value: attributes.content,
				onChange: ( content ) => setAttributes( { content } ),
				placeholder: __( 'Write 2code…', 'syntax-highlighting-code-block' ),
				'aria-label': __( 'Code', 'syntax-highlighting-code-block' ),
			};

			return <Fragment>
				<InspectorControls key="controls">
					<PanelBody
						title={ __( 'Syntax Highlighting', 'syntax-highlighting-code-block' ) }
						initialOpen={ true }
					>
						<PanelRow>
							<SelectControl
								label={ __( 'Language', 'syntax-highlighting-code-block' ) }
								value={ attributes.language }
								options={
									[
										{ label: __( 'Auto-detect', 'syntax-highlighting-code-block' ), value: '' },
										...sortedLanguageNames,
									]
								}
								onChange={ updateLanguage }
							/>
						</PanelRow>
						<PanelRow>
							<CheckboxControl
								label={ __( 'Show Line Numbers', 'syntax-highlighting-code-block' ) }
								checked={ attributes.showLines }
								onChange={ updateShowLines }
							/>
						</PanelRow>
					</PanelBody>
				</InspectorControls>
				{
					useLightBlockWrapper ?
						// This must be kept in sync with <https://github.com/WordPress/gutenberg/blob/master/packages/block-library/src/code/edit.js>.
						<BlockEditor.__experimentalBlock.pre>
							<PlainText
								{ ...plainTextProps }
								__experimentalVersion={ 2 }
								tagName="code"
							/>
						</BlockEditor.__experimentalBlock.pre> :
						<div key="editor-wrapper" className={ className }>
							<PlainText { ...plainTextProps } />
						</div>
				}
			</Fragment>;
		},

		save( { attributes } ) {
			return <pre>
				<code>{ attributes.content }</code>
			</pre>;
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
					const className = ( attributes.language ) ? 'language-' + attributes.language : '';
					return <pre>
						<code lang={ attributes.language } className={ className }>{ attributes.content }</code>
					</pre>;
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
