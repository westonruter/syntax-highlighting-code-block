/**
 * WordPress dependencies
 */
const { __ } = wp.i18n;
const { addFilter } = wp.hooks;
const { PlainText, InspectorControls } = wp.editor;
const { SelectControl } = wp.components;
const { Fragment } = wp.element;
const { sortBy } = lodash;

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
		},

		edit( { attributes, setAttributes, className } ) {
			const updateLanguage = ( language ) => {
				setAttributes( { language } );
			};

			const sortedLanguageNames = sortBy(
				Object.entries( languagesNames ).map( ( [ value, label ] ) => ( { label, value } ) ),
				( languageOption ) => languageOption.label.toLowerCase()
			);

			return <Fragment>
				<InspectorControls key="controls">
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
