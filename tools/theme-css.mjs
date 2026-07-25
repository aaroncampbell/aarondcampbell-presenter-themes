/* eslint-disable no-console -- This command reports build and drift status. */
/**
 * Build theme CSS or verify that committed CSS matches its Sass source.
 */

import { readFile, writeFile } from 'node:fs/promises';
import { resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

import * as sass from 'sass';

const rootDirectory = fileURLToPath( new URL( '../', import.meta.url ) );
const mode = process.argv[ 2 ];

const themes = [
	{
		name: 'Aaron',
		input: 'aaron/scss/aaron.scss',
		output: 'aaron/aaron.css',
	},
	{
		name: 'Aaron Purple',
		input: 'aaron-purple/scss/aaron-purple.scss',
		output: 'aaron-purple/aaron-purple.css',
	},
];

if ( ! [ 'build', 'check' ].includes( mode ) ) {
	console.error( 'Usage: node tools/theme-css.mjs <build|check>' );
	process.exitCode = 1;
} else {
	await processThemes();
}

async function processThemes() {
	const staleThemes = [];

	for ( const theme of themes ) {
		const inputPath = resolve( rootDirectory, theme.input );
		const outputPath = resolve( rootDirectory, theme.output );
		const result = sass.compile( inputPath, { style: 'expanded' } );
		const css = `${ result.css }\n`;

		if ( mode === 'build' ) {
			await writeFile( outputPath, css, 'utf8' );
			console.log( `Built ${ theme.output }` );
			continue;
		}

		const committedCss = await readFile( outputPath, 'utf8' );

		if ( committedCss !== css ) {
			staleThemes.push( theme );
		}
	}

	if ( staleThemes.length > 0 ) {
		for ( const theme of staleThemes ) {
			console.error(
				`${ theme.output } does not match ${ theme.input }. Run npm run build:css.`
			);
		}

		process.exitCode = 1;
		return;
	}

	if ( mode === 'check' ) {
		console.log( 'Theme CSS is current.' );
	}
}
