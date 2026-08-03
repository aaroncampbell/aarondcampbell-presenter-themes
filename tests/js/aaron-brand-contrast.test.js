const assert = require( 'node:assert/strict' );
const { readFile } = require( 'node:fs/promises' );
const path = require( 'node:path' );
const test = require( 'node:test' );

const themePath = path.resolve(
	__dirname,
	'../../aaron-brand/aaron-brand.css'
);

test( 'Aaron Brand keeps persistent chrome visible within dark slides and the viewport', async () => {
	const css = await readFile( themePath, 'utf8' );

	assert.match(
		css,
		/\.reveal\.has-dark-background \.persistent-twitter-link a \{\s*color: #fff;/
	);
	assert.match(
		css,
		/\.reveal\.has-dark-background \.persistent-twitter-link svg \{\s*fill: #fff;/
	);
	assert.match(
		css,
		/\.reveal\.has-dark-background \.persistent-twitter-link a:hover \{\s*color: #dcd1f7;/
	);
	assert.match(
		css,
		/\.reveal\.has-dark-background \.persistent-twitter-link a:hover svg \{\s*fill: #dcd1f7;/
	);
	assert.match(
		css,
		/\.reveal \.controls\[data-controls-layout=bottom-right\] \{\s*bottom: calc\(var\(--r-controls-spacing\) \+ 1em\);\s*right: calc\(var\(--r-controls-spacing\) \+ 3\.6em\);/
	);
} );
