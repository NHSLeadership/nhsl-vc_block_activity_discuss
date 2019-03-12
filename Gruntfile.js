/**
 * Gruntfile primarily for running stylelint and jslint locally and purging cache.
 *
 * http://gruntjs.com/ for the current plugin.
 *
 *
 * Requirements:
 * -------------
 * nodejs, npm, grunt-cli.
 *
 * Installation:
 * -------------
 * node and npm: instructions at http://nodejs.org/
 *
 * grunt-cli: `[sudo] npm install -g grunt-cli`
 *
 * node dependencies: run `npm install` in the root directory.
 *
 * Usage:
 * ------
 * Call tasks from the plugin root directory or the below.
 *
 * grunt localjs        Run eslint on files in amd/src/*.js.
 * grunt css            Run stylelint on the only css file in this plugin at present - styles.css.
 *
 * @package block
 * @subpackage activity_discuss
 * @author M Solanki - {@link https://moodle.org/user/profile.php?id=2227655}
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/* eslint-env node */

"use strict";

module.exports = function(grunt) {

    var path = require('path');

    // We need to include the core Moodle grunt file too, otherwise we can't run tasks like "amd".
    require("grunt-load-gruntfile")(grunt);
    grunt.loadGruntfile("../../Gruntfile.js");

    var PWD = process.cwd();

    var decachephp = '../../admin/cli/purge_caches.php';

    grunt.initConfig({
        exec: {
            decache: {
                cmd: 'php "' + decachephp + '"',
                callback: function(error) {
                    // Warning: Be careful when executing this task.  It may give
                    // file permission errors accessing Moodle because of the directory permissions
                    // for configured Moodledata directory if this is run as root.
                    // The exec process will output error messages.

                    // Just add one to confirm success.
                    if (!error) {
                        grunt.log.writeln("Moodle theme cache reset.");
                    }
                }
            }
        },
        watch: {
            eslint: {
                files: ["*.js"],
                tasks: ["eslint"],
            },
        },
        uglify: {
            options: {
                preserveComments: 'some'
            },
            localjsfiles: {
                files: grunt.file.expandMapping(
                    ['**/src/*.js', '!**/node_modules/**'],
                    '',
                    {
                        cwd: PWD,
                        rename: function(destBase, destPath) {
                            destPath = destPath.replace('src', 'build');
                            destPath = destPath.replace('.js', '.min.js');
                            destPath = path.resolve(PWD, destPath);
                            return destPath;
                        }
                    }
                )
            }
        },
        eslint: {
            // Even though warnings don't stop the build we don't display warnings by default because
            // at this moment we've got too many core warnings.
            // Check YUI module source files.
            localjsfiles: {src: ['**/amd/src/*.js', 'Gruntfile.js']}
        },
        stylelint: {
            localcss: {
                src: ['**/styles.css'],
                options: {
                    allowEmptyInput: true,
                    configOverrides: {
                        rules: {
                            // These rules have to be disabled in .stylelintrc for scss compat.
                            "at-rule-no-unknown": true,
                        }
                    }
                }
            }
        }
    });

    grunt.loadNpmTasks('grunt-stylelint');
    grunt.loadNpmTasks('grunt-eslint');
    grunt.loadNpmTasks('grunt-contrib-uglify');
    grunt.loadNpmTasks("grunt-exec");

    // Register tasks.
    grunt.registerTask('decache', ['exec:decache']);
    grunt.registerTask('localjs', ['eslint:localjsfiles', 'uglify', 'decache']);
    grunt.registerTask('localcss', ['stylelint:localcss']);
    grunt.registerTask("default", ['stylelint', 'eslint:localjsfiles', 'jshint', 'uglify', 'decache', 'watch']);
};
