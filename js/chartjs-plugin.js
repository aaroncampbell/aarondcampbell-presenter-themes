(function (global, factory) {
	typeof exports === 'object' && typeof module !== 'undefined' ? module.exports = factory() :
	typeof define === 'function' && define.amd ? define(factory) :
	(global = global || self, global.RevealChartjs = factory());
}(this, (function () { 'use strict';
	/**
	 * reveal.js plugin that adds support for additional chart.js functionality
	 */
	const Plugin = {

		id: 'chartjs',

		init: function( reveal ) {

			reveal.getRevealElement().addEventListener( 'mousedown', function( event ) {

				// When a fragment is shown
				reveal.on( 'fragmentshown', event => {
					// See if this fragment was to add a dataset to a graph and has specified a valid data-fragment-graph and data-fragment-graph-dataset
					if ( event.fragment.dataset.fragmentGraphDataset && window[event.fragment.dataset.fragmentGraphDataset]
						&& event.fragment.dataset.fragmentGraph && window[event.fragment.dataset.fragmentGraph] ) {
							// Push the dataset into the datasets of the graph, and set the length of the array as data-fragment-graph-dataset-location
							event.fragment.dataset.fragmentGraphDatasetLocation = window[event.fragment.dataset.fragmentGraph].data.datasets.push( window[event.fragment.dataset.fragmentGraphDataset] );
							// Trigger the graph redraw
							window[event.fragment.dataset.fragmentGraph].update();
					}
				} );

				// When a fragment is hidden
				reveal.on( 'fragmenthidden', event => {
					/** 
					 * See if this fragment was to add a dataset to a graph and has specified a valid data-fragment-graph and
					 * data-fragment-graph-dataset (only testing the latter for parity with the adding, we don't need it)
					 * Also check that it has the data-fragment-graph-dataset-location that we added
					 */
					if ( event.fragment.dataset.fragmentGraphDataset && window[event.fragment.dataset.fragmentGraphDataset]
						&& event.fragment.dataset.fragmentGraph && window[event.fragment.dataset.fragmentGraph]
						&& event.fragment.dataset.fragmentGraphDatasetLocation ) {
							// Remove the data from the graph dataset
							window[event.fragment.dataset.fragmentGraph].data.datasets.splice( event.fragment.dataset.fragmentGraphDatasetLocation - 1, 1 );
							// Trigger the graph redraw
							window[event.fragment.dataset.fragmentGraph].update();
					}
				} );
			} );

		}

	};

	return Plugin;
})));
