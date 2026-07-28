<?php
/**
 * Legacy Chart.js migration tests.
 *
 * @package AaronCampbellPresenterThemes
 */

use AaronCampbell\PresenterThemes\Legacy_Chartjs_Converter;

/** Verify safe, all-or-nothing conversion of characterized inline charts. */
final class Legacy_Chartjs_Converter_Test extends Companion_Test_Case {
	/** The historical market-share chart becomes a semantic line Chart block. */
	public function test_marketshare_line_chart_preserves_data_and_percent_semantics(): void {
		// phpcs:disable WordPress.WP.EnqueuedResources.NonEnqueuedScript -- Intentional inert migration fixture.
		$content = <<<'HTML'
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.5.1/chart.min.js"></script>
<canvas id="wp-marketshare-yearly-chart"></canvas>
<script>
var wpMarketshareChart = new Chart( 'wp-marketshare-yearly-chart' , {
    type: 'line',
    data: {
        labels: ['2011', '2012', 'Present'],
        datasets: [{
            label: 'WordPress',
            data: [13.1, 15.8, 42.4],
            fill: false,
            borderColor: '#8377D1',
            backgroundColor: '#8377D1',
        }]
    },
    options: {
        scales: {
            x: { grid: { color: '#8377D1' } },
            y: {
                grid: { color: '#8377D1' },
                beginAtZero: true,
                ticks: {
                    // Include a percent sign in the ticks.
                    callback: function(value, index, values) {
                        return value + '%';
                    }
                }
            },
        },
        elements: { point: { hitRadius: 10 } },
        plugins: {
            tooltip: {
                callbacks: {
                    label: function(context) {
                        console.log( context );
                        if ( context.dataset.label && context.formattedValue ) {
                            return context.dataset.label + ': ' + context.formattedValue + '%';
                        }
                        return '';
                    }
                }
            }
        }
    }
});
</script>
HTML;
		// phpcs:enable WordPress.WP.EnqueuedResources.NonEnqueuedScript

		$blocks = ( new Legacy_Chartjs_Converter() )->convert( null, $content );

		$this->assertIsArray( $blocks );
		$this->assertSame( array( 'presenter/chart' ), array_column( $blocks, 'blockName' ) );
		$this->assertSame( 'line', $blocks[0]['attrs']['chartType'] );
		$this->assertSame( array( 'Label', 'WordPress' ), $blocks[0]['attrs']['columns'] );
		$this->assertSame( array( array( '2011', 13.1 ), array( '2012', 15.8 ), array( 'Present', 42.4 ) ), $blocks[0]['attrs']['rows'] );
		$this->assertSame(
			array(
				'vAxis'       => array( 'minValue' => 0 ),
				'valueSuffix' => '%',
			),
			$blocks[0]['attrs']['options']
		);
	}

	/** A variable canvas target and theme-specific colors become a native bar chart. */
	public function test_bar_chart_with_variable_target_is_converted(): void {
		$content = <<<'HTML'
<h2>Faster Together</h2>
<canvas id="wp-contributors-by-version" width="900" height="450"></canvas>
<script>
var ctx = document.getElementById('wp-contributors-by-version');
var wpContributorsChart = new Chart(ctx, {
    type: 'bar',
    data: {
        labels: ['5.0', '5.1'],
        datasets: [{
            label: 'Contributors by WP Version',
            data: [588, 552],
            borderColor: 'rgb(131, 119, 209)',
            backgroundColor: 'rgb(131, 119, 209, 0.2)',
            borderWidth: 1
        }]
    },
    options: { scales: { y: { beginAtZero: true } } }
});
</script>
HTML;

		$blocks = ( new Legacy_Chartjs_Converter() )->convert( null, $content );

		$this->assertIsArray( $blocks );
		$this->assertSame( array( 'core/heading', 'presenter/chart' ), array_column( $blocks, 'blockName' ) );
		$this->assertSame( 'bar', $blocks[1]['attrs']['chartType'] );
		$this->assertSame( 900, $blocks[1]['attrs']['width'] );
		$this->assertSame( 450, $blocks[1]['attrs']['height'] );
		$this->assertSame( array( array( '5.0', 588 ), array( '5.1', 552 ) ), $blocks[1]['attrs']['rows'] );
	}

	/** Unsupported executable behavior preserves the complete HTML fallback. */
	public function test_unknown_javascript_fails_closed_without_partial_conversion(): void {
		$content = '<canvas id="chart"></canvas><script>alert("unexpected"); var chart = new Chart("chart", {type:"line",data:{labels:["A"],datasets:[{label:"Series",data:[1]}]},options:{}});</script>';

		$this->assertNull( ( new Legacy_Chartjs_Converter() )->convert( null, $content ) );
	}

	/** An earlier converter keeps ownership of the slide. */
	public function test_preceding_converter_result_is_unchanged(): void {
		$blocks = array( array( 'blockName' => 'core/paragraph' ) );

		$this->assertSame( $blocks, ( new Legacy_Chartjs_Converter() )->convert( $blocks, '<script>new Chart()</script>' ) );
	}
}
