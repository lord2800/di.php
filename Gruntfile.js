module.exports = function (grunt) {
	['phplint', 'phpcs', 'php-analyzer', 'phpunit', 'parallelize', 'gh-pages', 'contrib-watch']
		.forEach(function (name) { grunt.loadNpmTasks('grunt-' + name); });

	grunt.initConfig({
		phplint: {
			options: { swapPath: '/tmp' },
			app: ['src/**/*.php', 'index.php']
		},
		phpcs: {
			app: { dir: 'src' },
			options: { bin: 'vendor/bin/phpcs', standard: 'PSR1' }
		},
		php_analyzer: {
			options: { bin: 'vendor/bin/phpalizer' },
			app: { dir: 'src' }
		},
		phpunit: {
			unit: { dir: 'tests/' },
			options: {
				bin: 'vendor/bin/phpunit',
				bootstrap: 'tests/Bootstrap.php',
				colors: true
			}
		},
		watch: {
			test: {
				files: ['tests/**/*.php'],
				tasks: ['phplint', 'phpunit']
			}
		}
	});

	grunt.registerTask('precommit', ['parallelize:phplint', 'phpcs', 'phpunit']);
	grunt.registerTask('test', ['phplint', 'phpcs', 'php_analyzer', 'phpunit']);
	grunt.registerTask('default', ['test']);
};
