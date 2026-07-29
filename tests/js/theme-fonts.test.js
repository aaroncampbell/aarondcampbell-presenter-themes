const assert = require( 'node:assert/strict' );
const { readFile, stat } = require( 'node:fs/promises' );
const path = require( 'node:path' );
const test = require( 'node:test' );

const rootDirectory = path.resolve( __dirname, '../..' );
const openSansFaces = [
	'OpenSans-Regular',
	'OpenSans-Italic',
	'OpenSans-SemiBold',
	'OpenSans-Bold',
];
const themes = [
	{
		fontFaces: openSansFaces,
		name: 'aaron',
	},
	{
		fontFaces: openSansFaces,
		name: 'aaron-purple',
	},
	{
		fontFaces: [
			...openSansFaces,
			'Poppins-Regular',
			'Poppins-Medium',
			'Poppins-SemiBold',
			'Poppins-ExtraBold',
		],
		name: 'aaron-brand',
	},
];

test( 'themes prefer valid compressed font faces', async () => {
	for ( const theme of themes ) {
		const themeDirectory = path.join( rootDirectory, theme.name );
		const fontDirectory = path.join( themeDirectory, 'fonts' );
		const css = await readFile(
			path.join( themeDirectory, `${ theme.name }.css` ),
			'utf8'
		);

		for ( const fontFace of theme.fontFaces ) {
			const woff2Path = path.join( fontDirectory, fontFace + '.woff2' );
			const ttfPath = path.join( fontDirectory, fontFace + '.ttf' );
			const [ woff2, woff2Stats, ttfStats ] = await Promise.all( [
				readFile( woff2Path ),
				stat( woff2Path ),
				stat( ttfPath ),
			] );

			assert.equal( woff2.subarray( 0, 4 ).toString( 'ascii' ), 'wOF2' );
			assert.ok( woff2Stats.size < ttfStats.size );
			assert.ok(
				css.includes(
					'src: url("fonts/' +
						fontFace +
						'.woff2") format("woff2"), url("fonts/' +
						fontFace +
						'.ttf") format("truetype");'
				)
			);
		}
	}
} );
