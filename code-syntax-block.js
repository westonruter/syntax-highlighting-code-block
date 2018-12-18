/**
 * Code Syntax Highlighting Block
 * A gutenberg block that allows inserting code with syntax highlighting.
 */

const html = htm.bind( wp.element.createElement );
const { __ } = wp.i18n;
const { addFilter } = wp.hooks;
const { PlainText, InspectorControls } = wp.editor;
const { SelectControl } = wp.components;
const { Fragment } = wp.element;

/*
 * Available languages, which corresponds to the files located in vendor/scrivo/highlight.php/Highlight/languages.
 * The strings are used here for the sake of translations. An array is used as opposed to an object because objects
 * in JS do not preserve order like associative arrays in PHP.
 */
const languageOptions = [
	{
		value: 'bash',
		label: __( 'Bash (shell)', 'code-syntax-block' )
	},
	{
		value: 'cpp',
		label: __( 'C-like', 'code-syntax-block' )
	},
	{
		value: 'css',
		label: __( 'CSS', 'code-syntax-block' )
	},
	{
		value: 'diff',
		label: __( 'Diff', 'code-syntax-block' )
	},
	{
		value: 'go',
		label: __( 'Go (golang)', 'code-syntax-block' )
	},
	{
		value: 'xml',
		label: __( 'HTML/Markup', 'code-syntax-block' )
	},
	{
		value: 'javascript',
		label: __( 'JavaScript (JSX)', 'code-syntax-block' )
	},
	{
		value: 'json',
		label: __( 'JSON', 'code-syntax-block' )
	},
	{
		value: 'markdown',
		label: __( 'Markdown', 'code-syntax-block' )
	},
	{
		value: 'php',
		label: __( 'PHP', 'code-syntax-block' )
	},
	{
		value: 'python',
		label: __( 'Python', 'code-syntax-block' )
	},
	{
		value: 'sql',
		label: __( 'SQL', 'code-syntax-block' )
	}
];

const addSyntaxToCodeBlock = ( settings ) => {
	if ( 'core/code' !== settings.name ) {
		return settings;
	}

	return {
		...settings,

		attributes: {
			...settings.attributes,
			language: {
				type: 'string'
			}
		},

		edit({ attributes, setAttributes, isSelected, className }) {
			const updateLanguage = language => {
				setAttributes({ language });
			};

			// Note: Use of Fragment can be eliminated after https://github.com/developit/htm/issues/15.
			return html`
				<${Fragment}>
					<${InspectorControls} key="controls">
						<${SelectControl}
							label=${ __( 'Language', 'code-syntax-block' ) }
							value=${ attributes.language }
							options=${
								[
									{ label: __( 'Auto-detect', 'code-syntax-block' ), value: '' },
									...languageOptions
								]
							}
							onChange=${ updateLanguage }
						/>
					</${InspectorControls}>
					<div key="editor-wrapper" className=${ className }>
						<${PlainText}
							value=${ attributes.content }
							onChange=${ ( content ) => setAttributes({ content }) }
							placeholder=${ __( 'Write code…', 'code-syntax-block' ) }
							aria-label=${ __( 'Code', 'code-syntax-block' ) }
						/>
						<div className="language-selected">${ languageOptions[ attributes.language ] }</div>
					</div>
				</${Fragment}>
			`;
		},

		save({ attributes }) {
			return html`
				<pre><code>${ attributes.content }</code></pre>
			`;
		},

		// Automatically convert core code blocks to this new extended code block.
		deprecated: [
			...( settings.deprecated || []),
			{
				attributes: {
					...settings.attributes,
					language: {
						type: 'string'
					}
				},

				save: function({ attributes }) {
					const className = ( attributes.language ) ? 'language-' + attributes.language : '';
					return html`
						<pre><code lang=${ attributes.language } className=${ className }>${ attributes.content }</code></pre>
					`;
				}
			}
		]
	};
};

// Register Filter
addFilter(
	'blocks.registerBlockType',
	'westonruter/code-syntax-block',
	addSyntaxToCodeBlock
);
