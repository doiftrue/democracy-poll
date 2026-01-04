const gulp            = require('gulp')
const terser          = require('gulp-terser')
const noop            = require('gulp-noop')
const source          = require('vinyl-source-stream')
const buffer          = require('vinyl-buffer')
const rollupStream    = require('@rollup/stream')
const babel           = require('@rollup/plugin-babel').default;
const commonjs        = require('@rollup/plugin-commonjs')
const json            = require('@rollup/plugin-json')
const { nodeResolve } = require('@rollup/plugin-node-resolve')


const build = gulp.series( bundleJs.bind( null, true ) )
const watch = gulp.series( build, watchFiles )

// Export for CLI
exports.build = build;
exports.default = build;
exports.watch = watch;

// Handle graceful shutdown
process.on( 'SIGINT', function(){
	console.log( '\nStopping watch task...' )
	process.exit( 0 )
} )

/// FUNCTIONS ///

let rollupCache
function bundleJs( isBuild ){
	const input = 'js/democracy.mjs'
	const dest = 'js/democracy.min.js'

	return rollupStream( {
		input  : input,
		cache  : rollupCache,
		output : {
			format              : 'iife',
			name                : 'DemocracyPoll',
			sourcemap           : true,
			inlineDynamicImports: true
		},
		plugins: [
			json(),                       // to use only needed from block.json
			nodeResolve( { browser: true } ), // browser-compatible package build, not the Node variant
			commonjs(),                   // convert CommonJS modules to ES6
			babel( {
				extensions  : ['.js'],
				babelHelpers: 'bundled',
				presets     : [
					['@babel/preset-env', {
						targets : { esmodules: true },
						modules : false,
						bugfixes: true
					}]
				]
			} )
		],
		onwarn( warning, warn ){
			// Eval usage is intentional for server-provided callbacks.
			if( warning.code === 'EVAL' ){
				return
			}
			warn( warning )
		}
	} )
		.on( 'bundle', function( bundle ){
			rollupCache = bundle?.cache; // INFO: persist cache to rebuilds can reuse previous graph info
		} )
		.on( 'error', function( err ){
			console.error( 'Rollup error:', err && err.message ? err.message : err )
			this.emit( 'end' )
		} )
		.pipe( source( dest ) )
		.pipe( buffer() )
		.pipe( isBuild ? terser() : noop() ) // minify only for build
		.pipe( gulp.dest( '.' ) )
}

function watchFiles(){
	gulp.watch(
		['js/**/*.mjs', '!js/**/*.min.js'],
		{ ignoreInitial: true },
		bundleJs.bind( null, false )
	)
}

