( function ( root, factory ) {
	'use strict';

	const plugin = factory( root );

	if ( typeof module === 'object' && module.exports ) {
		module.exports = plugin;
	}

	if ( typeof root.define === 'function' && root.define.amd ) {
		root.define( () => plugin );
	}

	root.RevealChartjs = plugin;

	if (
		root.presenterReveal &&
		typeof root.presenterReveal.registerPlugin === 'function'
	) {
		root.presenterReveal.registerPlugin( 'chartjs', plugin );
	}
} )( typeof window === 'object' ? window : global, ( root ) => {
	'use strict';

	const initializedRevealInstances = new WeakSet();
	const activeFragments = new WeakMap();
	const activeInsertions = new Set();

	/**
	 * Resolve the chart and dataset referenced by one Reveal fragment.
	 *
	 * Existing presentations store global variable names in data attributes, so
	 * this deliberately performs only direct global-property lookups.
	 *
	 * @param {Element} fragment Reveal fragment element.
	 * @return {?{ chart: Object, dataset: Object }} Resolved Chart.js values.
	 */
	function resolveFragmentValues( fragment ) {
		const graphName = fragment?.dataset?.fragmentGraph;
		const datasetName = fragment?.dataset?.fragmentGraphDataset;

		if ( ! graphName || ! datasetName ) {
			return null;
		}

		const chart = root[ graphName ];
		const dataset = root[ datasetName ];

		if (
			! chart ||
			! chart.data ||
			! Array.isArray( chart.data.datasets ) ||
			typeof chart.update !== 'function' ||
			! dataset ||
			typeof dataset !== 'object'
		) {
			return null;
		}

		return { chart, dataset };
	}

	/**
	 * Keep the legacy one-based location attribute synchronized after removal.
	 *
	 * @param {Object} chart        Chart whose datasets changed.
	 * @param {number} removedIndex Zero-based removed dataset index.
	 */
	function shiftLaterInsertions( chart, removedIndex ) {
		for ( const insertion of activeInsertions ) {
			if (
				insertion.chart !== chart ||
				insertion.index <= removedIndex
			) {
				continue;
			}

			insertion.index -= 1;
			insertion.fragment.dataset.fragmentGraphDatasetLocation = String(
				insertion.index + 1
			);
		}
	}

	/**
	 * Add a referenced dataset when its fragment becomes visible.
	 *
	 * @param {Object} event Reveal fragment event.
	 */
	function showDataset( event ) {
		const fragment = event?.fragment;

		if ( ! fragment || activeFragments.has( fragment ) ) {
			return;
		}

		const resolved = resolveFragmentValues( fragment );

		if ( ! resolved ) {
			return;
		}

		const insertion = {
			...resolved,
			fragment,
			index: resolved.chart.data.datasets.length,
		};

		resolved.chart.data.datasets.push( resolved.dataset );
		fragment.dataset.fragmentGraphDatasetLocation = String(
			insertion.index + 1
		);
		activeFragments.set( fragment, insertion );
		activeInsertions.add( insertion );
		resolved.chart.update();
	}

	/**
	 * Remove only the dataset insertion owned by the hidden fragment.
	 *
	 * @param {Object} event Reveal fragment event.
	 */
	function hideDataset( event ) {
		const fragment = event?.fragment;
		const insertion = fragment ? activeFragments.get( fragment ) : null;

		if ( ! insertion ) {
			return;
		}

		activeFragments.delete( fragment );
		activeInsertions.delete( insertion );
		delete fragment.dataset.fragmentGraphDatasetLocation;

		if (
			insertion.chart.data.datasets[ insertion.index ] !==
			insertion.dataset
		) {
			return;
		}

		insertion.chart.data.datasets.splice( insertion.index, 1 );
		shiftLaterInsertions( insertion.chart, insertion.index );
		insertion.chart.update();
	}

	return {
		id: 'chartjs',

		/**
		 * Register fragment behavior once for one Reveal instance.
		 *
		 * @param {Object} reveal Reveal instance supplied by Reveal.js.
		 */
		init( reveal ) {
			if (
				! reveal ||
				typeof reveal !== 'object' ||
				typeof reveal.on !== 'function' ||
				initializedRevealInstances.has( reveal )
			) {
				return;
			}

			initializedRevealInstances.add( reveal );
			reveal.on( 'fragmentshown', showDataset );
			reveal.on( 'fragmenthidden', hideDataset );
		},
	};
} );
