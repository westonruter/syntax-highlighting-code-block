/**
 * ESLint flat config.
 *
 * Extends the shared configuration shipped with `@wordpress/scripts` (which is
 * based on the WordPress coding standards, including `@wordpress/eslint-plugin`
 * recommended) and layers the project-specific settings and rules on top.
 */

/**
 * External dependencies
 */
const globals = require('globals');
// @ts-ignore -- No declaration file for this module.
const wpScriptsConfig = require('@wordpress/scripts/config/eslint.config.cjs');

// Build tooling and root-level config files — Node CommonJS, not browser code.
const nodeToolingFiles = ['Gruntfile.js', '*.config.js'];

module.exports = [
	...wpScriptsConfig,
	{
		// Ignore patterns in addition to those provided by @wordpress/scripts.
		// Replaces the previous `.eslintignore` (`*/*` + `!src/*`) and the
		// `--ignore-path=.gitignore`, neither of which ESLint's flat config
		// supports. Only root-level files and `src/` contain lintable code.
		ignores: [
			'build/',
			'dist/',
			'vendor/',
			'/syntax-highlighting-code-block/',
			'**/*.min.js',
		],
	},
	{
		// Browser globals for client-side scripts (everything but Node tooling).
		files: ['**/*.js'],
		ignores: nodeToolingFiles,
		languageOptions: {
			globals: {
				...globals.browser,
			},
		},
		settings: {
			'import/resolver': {
				node: {
					extensions: ['.js'],
				},
			},
			react: {
				pragma: 'wp',
				version: 'detect',
			},
		},
		rules: {
			'@wordpress/i18n-text-domain': [
				'error',
				{
					allowedTextDomain: [
						'syntax-highlighting-code-block',
						'default',
					],
				},
			],
			'@wordpress/i18n-hyphenated-range': ['off'],
		},
	},
	{
		// Node-based build tooling (Gruntfile, root config files). Replaces the
		// `/* eslint-env node */` and `/* eslint-disable ... */` directives that
		// previously lived at the top of Gruntfile.js.
		files: nodeToolingFiles,
		languageOptions: {
			sourceType: 'commonjs',
			globals: {
				...globals.node,
			},
		},
		rules: {
			camelcase: 'off',
			'no-console': 'off',
			'no-param-reassign': 'off',
		},
	},
];
