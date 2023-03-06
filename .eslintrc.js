module.exports = {
	root: true,
	extends: ['plugin:@wordpress/eslint-plugin/recommended'],
	settings: {
		react: {
			pragma: 'wp',
			version: '16.6',
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
	},
};
