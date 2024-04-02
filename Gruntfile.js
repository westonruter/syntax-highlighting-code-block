/* eslint-env node */
/* eslint-disable camelcase, no-console, no-param-reassign */

module.exports = function (grunt) {
	'use strict';

	grunt.initConfig({
		pkg: grunt.file.readJSON('package.json'),

		// Deploys a git Repo to the WordPress SVN repo.
		wp_deploy: {
			deploy: {
				options: {
					plugin_slug: 'syntax-highlighting-code-block',
					build_dir: 'syntax-highlighting-code-block',
					assets_dir: '.wordpress-org',
				},
			},
		},
	});

	// Load tasks.
	grunt.loadNpmTasks('grunt-wp-deploy');

	// Register tasks.
	grunt.registerTask('default', ['wp_deploy']);
};
