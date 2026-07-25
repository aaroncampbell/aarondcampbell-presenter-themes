const assert = require( 'node:assert/strict' );
const test = require( 'node:test' );

const pluginPath = require.resolve( '../../js/chartjs-plugin' );

function loadPlugin( presenterReveal = undefined ) {
	delete require.cache[ pluginPath ];
	delete global.RevealChartjs;

	if ( presenterReveal ) {
		global.presenterReveal = presenterReveal;
	} else {
		delete global.presenterReveal;
	}

	return require( pluginPath );
}

function createReveal() {
	const handlers = new Map();
	const registrations = [];

	return {
		emit( name, fragment ) {
			for ( const handler of handlers.get( name ) || [] ) {
				handler( { fragment } );
			}
		},
		on( name, handler ) {
			registrations.push( name );
			handlers.set( name, [
				...( handlers.get( name ) || [] ),
				handler,
			] );
		},
		registrations,
	};
}

function createFragment( graphName, datasetName ) {
	return {
		dataset: {
			fragmentGraph: graphName,
			fragmentGraphDataset: datasetName,
		},
	};
}

test.afterEach( () => {
	delete global.define;
	delete global.presenterReveal;
	delete global.RevealChartjs;
	for ( const name of [ 'testChart', 'firstDataset', 'secondDataset' ] ) {
		delete global[ name ];
	}
} );

test( 'exports one shared legacy global and native Presenter plugin', () => {
	const registrations = [];
	const presenterReveal = {
		registerPlugin( id, plugin ) {
			registrations.push( { id, plugin } );
		},
	};
	const plugin = loadPlugin( presenterReveal );

	assert.equal( plugin.id, 'chartjs' );
	assert.equal( global.RevealChartjs, plugin );
	assert.deepEqual( registrations, [ { id: 'chartjs', plugin } ] );
} );

test( 'retains the legacy global when the native API is unavailable', () => {
	const plugin = loadPlugin();

	assert.equal( global.RevealChartjs, plugin );
} );

test( 'retains the historical AMD export alongside the legacy global', () => {
	let factory;
	global.define = ( registeredFactory ) => {
		factory = registeredFactory;
	};
	global.define.amd = {};

	const plugin = loadPlugin();

	assert.equal( factory(), plugin );
	assert.equal( global.RevealChartjs, plugin );
} );

test( 'registers fragment listeners immediately and only once', () => {
	const plugin = loadPlugin();
	const reveal = createReveal();

	plugin.init( reveal );
	plugin.init( reveal );

	assert.deepEqual( reveal.registrations, [
		'fragmentshown',
		'fragmenthidden',
	] );
} );

test( 'show and hide are repeat-safe and restore the original datasets', () => {
	const plugin = loadPlugin();
	const reveal = createReveal();
	const original = { label: 'original' };
	const inserted = { label: 'inserted' };
	const updates = [];
	const fragment = createFragment( 'testChart', 'firstDataset' );
	global.testChart = {
		data: { datasets: [ original ] },
		update() {
			updates.push( [ ...this.data.datasets ] );
		},
	};
	global.firstDataset = inserted;
	plugin.init( reveal );

	reveal.emit( 'fragmentshown', fragment );
	reveal.emit( 'fragmentshown', fragment );
	assert.deepEqual( global.testChart.data.datasets, [ original, inserted ] );
	assert.equal( fragment.dataset.fragmentGraphDatasetLocation, '2' );
	assert.equal( updates.length, 1 );

	reveal.emit( 'fragmenthidden', fragment );
	reveal.emit( 'fragmenthidden', fragment );
	assert.deepEqual( global.testChart.data.datasets, [ original ] );
	assert.equal( fragment.dataset.fragmentGraphDatasetLocation, undefined );
	assert.equal( updates.length, 2 );
} );

test( 'out-of-order hides remove the insertion owned by each fragment', () => {
	const plugin = loadPlugin();
	const reveal = createReveal();
	const original = { label: 'original' };
	const shared = { label: 'shared' };
	const first = createFragment( 'testChart', 'firstDataset' );
	const second = createFragment( 'testChart', 'secondDataset' );
	let updates = 0;
	global.testChart = {
		data: { datasets: [ original ] },
		update() {
			updates += 1;
		},
	};
	global.firstDataset = shared;
	global.secondDataset = shared;
	plugin.init( reveal );

	reveal.emit( 'fragmentshown', first );
	reveal.emit( 'fragmentshown', second );
	assert.deepEqual( global.testChart.data.datasets, [
		original,
		shared,
		shared,
	] );
	assert.equal( second.dataset.fragmentGraphDatasetLocation, '3' );

	reveal.emit( 'fragmenthidden', first );
	assert.deepEqual( global.testChart.data.datasets, [ original, shared ] );
	assert.equal( second.dataset.fragmentGraphDatasetLocation, '2' );

	reveal.emit( 'fragmenthidden', second );
	assert.deepEqual( global.testChart.data.datasets, [ original ] );
	assert.equal( updates, 4 );
} );

test( 'malformed fragment references are ignored without chart updates', () => {
	const plugin = loadPlugin();
	const reveal = createReveal();
	let updates = 0;
	global.testChart = {
		data: { datasets: [] },
		update() {
			updates += 1;
		},
	};
	plugin.init( reveal );

	assert.doesNotThrow( () => {
		reveal.emit( 'fragmentshown', null );
		reveal.emit( 'fragmentshown', { dataset: {} } );
		reveal.emit(
			'fragmentshown',
			createFragment( 'missingChart', 'firstDataset' )
		);
		reveal.emit(
			'fragmentshown',
			createFragment( 'testChart', 'missingDataset' )
		);
		reveal.emit( 'fragmenthidden', { dataset: {} } );
	} );
	assert.equal( updates, 0 );
	assert.deepEqual( global.testChart.data.datasets, [] );
} );
