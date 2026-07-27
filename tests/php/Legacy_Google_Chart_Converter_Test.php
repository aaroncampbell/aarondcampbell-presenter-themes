<?php
/**
 * Legacy Google Chart migration tests.
 *
 * @package AaronCampbellPresenterThemes
 */

use AaronCampbell\PresenterThemes\Legacy_Google_Chart_Converter;

/** Verify safe, all-or-nothing conversion. */
final class Legacy_Google_Chart_Converter_Test extends Companion_Test_Case {
	/** Detached scripts are paired to containers by literal target ID. */
	public function test_two_detached_scripts_are_joined_to_their_target_containers(): void {
		// phpcs:disable WordPress.WP.EnqueuedResources.NonEnqueuedScript -- Intentional inert migration fixture.
		$content = <<<'HTML'
<h2>WordPress % of Internet</h2>
<p class="fragment" data-fragment-index="1">Extrapolated Terribly to # Sites</p>
<script src="https://www.gstatic.com/charts/loader.js"></script>
<div id="chart_percent_div" class="fragment swap-out block" style="width: 800px; height: 400px; margin: 0 auto;" data-fragment-index="1"></div>
<div id="chart_sites_div" class="fragment swap-in block" style="width: 800px; height: 400px; display: block; margin: 0 auto;" data-fragment-index="1"></div>
<script>
google.charts.load('current', {'packages':['corechart']});
var data = google.visualization.arrayToDataTable([
  [{label:'Year', type:'date'}, 'Percent'],
  [new Date(2012, 0, 1), 13.1],
  [new Date(2013, 0, 1), 17.4]
]);
var options = {legend:{position:'none'}, vAxis:{minValue:0, maxValue:40, textStyle:{color:'#8377D1'}}};
var chart = new google.visualization.LineChart(document.getElementById('chart_percent_div'));
chart.draw(data, options);
</script>
<script>
var data = google.visualization.arrayToDataTable([
  ['Year', 'Sites'],
  ['2012', 346004403*.131],
  ['2013', 500000000*.174]
]);
var options = {legend:{position:'none'}, hAxis:{title:'Year'}};
var chart = new google.visualization.LineChart(document.getElementById('chart_sites_div'));
chart.draw(data, options);
</script>
HTML;
		// phpcs:enable WordPress.WP.EnqueuedResources.NonEnqueuedScript

		$blocks = ( new Legacy_Google_Chart_Converter() )->convert( null, $content );

		$this->assertIsArray( $blocks );
		$this->assertSame( array( 'core/heading', 'core/paragraph', 'presenter/chart', 'presenter/chart' ), array_column( $blocks, 'blockName' ) );
		$this->assertSame( 45_326_576.793, $blocks[3]['attrs']['rows'][0][1] );
		$this->assertSame( '2012-01-01', $blocks[2]['attrs']['rows'][0][0] );
		$this->assertSame( 'swap-out block', $blocks[2]['attrs']['presenterFragmentCustomClasses'] );
		$this->assertSame( 'swap-in block', $blocks[3]['attrs']['presenterFragmentCustomClasses'] );
		$this->assertSame( 1, $blocks[2]['attrs']['presenterFragmentIndex'] );
		$this->assertArrayNotHasKey( 'textStyle', $blocks[2]['attrs']['options']['vAxis'] );
	}

	/** Unsupported expressions preserve the original Custom HTML fallback. */
	public function test_unknown_javascript_fails_closed_without_partial_conversion(): void {
		$content = '<div id="chart"></div><script>var data = google.visualization.arrayToDataTable(makeData()); var options = {}; var chart = new google.visualization.LineChart(document.getElementById("chart")); chart.draw(data, options);</script>';

		$this->assertNull( ( new Legacy_Google_Chart_Converter() )->convert( null, $content ) );
	}
}
