module.exports = {
	root: true,
	extends: ['plugin:@wordpress/eslint-plugin/recommended'],
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
	env: {
		browser: true,
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
};
