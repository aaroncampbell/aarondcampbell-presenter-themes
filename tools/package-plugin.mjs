/* eslint-disable no-console -- This command reports the created package path. */
/**
 * Build the separately distributed plugin archive from committed files.
 */

import { mkdirSync, rmSync } from 'node:fs';
import { readFile } from 'node:fs/promises';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { spawnSync } from 'node:child_process';

const pluginDirectory = dirname( dirname( fileURLToPath( import.meta.url ) ) );
const packageMetadata = JSON.parse(
	await readFile( join( pluginDirectory, 'package.json' ), 'utf8' )
);
const archiveDirectory = join( pluginDirectory, 'dist' );
const archiveName = `${ packageMetadata.name }-${ packageMetadata.version }.zip`;
const archivePath = join( archiveDirectory, archiveName );
const archivePrefix = `${ packageMetadata.name }/`;

const status = spawnSync(
	'git',
	[ 'status', '--porcelain', '--untracked-files=no' ],
	{
		cwd: pluginDirectory,
		encoding: 'utf8',
	}
);

if ( status.status !== 0 ) {
	throw new Error( status.stderr || 'Could not inspect the Git worktree.' );
}

if ( status.stdout.trim() ) {
	throw new Error(
		'Commit tracked changes before building a plugin package.'
	);
}

mkdirSync( archiveDirectory, { recursive: true } );
rmSync( archivePath, { force: true } );

const archive = spawnSync(
	'git',
	[
		'archive',
		'--format=zip',
		`--prefix=${ archivePrefix }`,
		`--output=${ archivePath }`,
		'HEAD',
	],
	{
		cwd: pluginDirectory,
		encoding: 'utf8',
	}
);

if ( archive.status !== 0 ) {
	throw new Error( archive.stderr || 'Could not build the plugin package.' );
}

console.log( archivePath );
