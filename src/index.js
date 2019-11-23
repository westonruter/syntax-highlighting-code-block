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
				<div key="editor-wrapper" className={ className }>
					<PlainText
						value={ attributes.content }
						onChange={ ( content ) => setAttributes( { content } ) }
						placeholder={ __( 'Write code…', 'syntax-highlighting-code-block' ) }
						aria-label={ __( 'Code', 'syntax-highlighting-code-block' ) }
					/>
				</div>
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
