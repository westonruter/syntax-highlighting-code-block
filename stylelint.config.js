const path = require('path');

/**
 * @type {import('stylelint').Config}
 */
const config = {
	// Resolved through @wordpress/scripts rather than extended by bare name.
	// @wordpress/stylelint-config is a transitive dependency which npm nests
	// inside @wordpress/scripts instead of hoisting to the project root, since
	// @wordpress/block-editor holds a conflicting stylelint version there.
	extends: require.resolve('@wordpress/stylelint-config', {
		paths: [
			path.dirname(require.resolve('@wordpress/scripts/package.json')),
		],
	}),
};

module.exports = config;
