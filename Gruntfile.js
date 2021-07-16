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
					build_dir: 'dist',
					assets_dir: '.wordpress-org',
				},
			},
		},
	});

	// Load tasks.
	grunt.loadNpmTasks('grunt-contrib-copy');
	grunt.loadNpmTasks('grunt-wp-deploy');

	// Register tasks.
	grunt.registerTask('default', ['dist']);

	grunt.registerTask('dist', function () {
		const done = this.async();
		const spawnQueue = [];
		const stdout = [];

		spawnQueue.push({
			cmd: 'git',
			args: ['--no-pager', 'log', '-1', '--format=%h', '--date=short'],
		});

		function finalize() {
			const commitHash = stdout.shift();
			const versionAppend =
				new Date()
					.toISOString()
					.replace(/\.\d+/, '')
					.replace(/-|:/g, '') +
				'-' +
				commitHash;

			const paths = [
				'syntax-highlighting-code-block.php',
				'language-names.php',
				'editor-styles.css',
				'style.css',
				'README.md',
				'LICENSE',
				'build/*',
			];

			grunt.config.set('copy', {
				build: {
					src: paths,
					dest: 'dist',
					expand: true,
					options: {
						noProcess: ['*/**', 'LICENSE'], // That is, only process syntax-highlighting-code-block.php and README.md.
						process(content, srcpath) {
							if (
								!/syntax-highlighting-code-block\.php$/.test(
									srcpath
								)
							) {
								return content;
							}
							let updatedContent = content;
							const versionRegex =
								/(\*\s+Version:\s+)(\d+(\.\d+)+-\w+)/;
							let version;

							// If not a stable build (e.g. 0.7.0-beta), amend the version with the git commit and current timestamp.
							const matches = content.match(versionRegex);
							if (matches) {
								version = matches[2] + '-' + versionAppend;
								console.log(
									'Updating version in plugin version to ' +
										version
								);
								updatedContent = updatedContent.replace(
									versionRegex,
									'$1' + version
								);
								updatedContent = updatedContent.replace(
									/(const PLUGIN_VERSION = ')(.+?)(?=')/,
									'$1' + version
								);
							}

							updatedContent = updatedContent.replace(
								/const DEVELOPMENT_MODE = true;.*/,
								'const DEVELOPMENT_MODE = false;'
							);

							return updatedContent;
						},
					},
				},
				composer: {
					src: ['vendor/autoload.php', 'vendor/composer/**'],
					dest: 'dist',
					expand: true,
					options: {
						noProcess: ['vendor/composer/installed.json'],
						process(content) {
							return content.replace(
								/\/highlight\.php/g,
								'/highlight-php'
							);
						},
					},
				},
				vendor: {
					src: ['Highlight/**', 'HighlightUtilities/**', 'styles/*'],
					expand: true,
					cwd: 'vendor/scrivo/highlight.php/',
					dest: 'dist/vendor/scrivo/highlight-php/',
				},
			});
			grunt.task.run('copy');

			done();
		}

		function doNext() {
			const nextSpawnArgs = spawnQueue.shift();
			if (!nextSpawnArgs) {
				finalize();
			} else {
				grunt.util.spawn(nextSpawnArgs, function (err, res) {
					if (err) {
						throw new Error(err.message);
					}
					stdout.push(res.stdout);
					doNext();
				});
			}
		}

		doNext();
	});
};
