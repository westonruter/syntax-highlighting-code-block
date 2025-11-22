/**
 * @type {import('lint-staged', { with: { 'resolution-mode': 'import' } }).Configuration}
 */
const config = {
	'*.{js,ts,mjs}': [ 'npm run lint:js', () => 'npx tsc' ],
	'*.css': [ 'npm run lint:css' ],
	'composer.{json,lock}': [
		() => 'composer validate --strict',
		() => 'composer normalize --dry-run',
	],
	'*.php': [
		'composer phpcs',
		() => 'composer phpstan',
		() => 'npm run verify-version-consistency',
	],
	'*.md': [ 'npm run lint:md', 'npm run verify-version-consistency' ],
	'README.md': [
		() => 'npm run verify-version-consistency',
		() => 'npm run transform-readme',
	],
};

module.exports = config;
