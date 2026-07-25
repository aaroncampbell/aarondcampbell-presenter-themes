# Aaron D. Campbell Presenter Themes

This private WordPress plugin provides AaronDCampbell.com-specific themes and
presentation customizations for [Presenter](https://github.com/aaroncampbell/presenter).
It is installed and distributed separately and is never packaged with Presenter.

## Requirements

-   WordPress 7.0 or later
-   PHP 8.3 or later
-   Presenter
-   Node.js 24 and npm 11 for JavaScript development

## Development

Install the PHP development dependencies and run the quality checks:

```sh
composer install
composer check
composer phpcs
composer check:audit
```

Install the dependency-free Node workspace and run the shipped JavaScript's
syntax and behavior checks:

```sh
npm ci
npm run check
npm run check:audit
```

`npm run check` runs `node --check` against the production Chart integration
and its focused `node:test` suite. CI applies WordPress JavaScript lint and
formatting standards through Presenter's pinned development toolchain. The
JavaScript is shipped directly; there is no bundler or generated JavaScript
output.

The integration tests use Presenter's shared `wp-env` WordPress installation.
In the adjacent `presenter` repository, copy `.wp-env.override.example.json` to
the ignored `.wp-env.override.json` so the local companion checkout is mounted.
Then start the environment and run:

```sh
npm run env:start
npx wp-env run tests-cli --env-cwd=wp-content/plugins/aarondcampbell-presenter-themes -- vendor/bin/phpunit --configuration=phpunit.xml.dist
```

The checked-in CSS remains a compatibility asset. Its modern build pipeline is
being established separately; do not regenerate it with an unpinned local
toolchain.

After committing a release, build the separately distributed plugin archive:

```sh
npm run package
```

The archive is created from `HEAD` under `dist/`. Git export rules exclude
tests, source styles, maps, dependency manifests, CI, and contributor tooling;
the archive retains only the runtime PHP, JavaScript, compiled themes, fonts,
images, license, and WordPress readme.

## Chart integration

The `RevealChartjs` script is a Reveal plugin for fragment-driven changes to an
authored chart. It does **not** include Chart.js, create charts, or fetch chart
data. A deck is responsible for loading its chosen chart library and exposing
both the chart and each fragment-controlled dataset as global properties before
Reveal emits fragment events.

Two data attributes connect a fragment to those authored globals:

```html
<span
	class="fragment"
	data-fragment-graph="marketShareChart"
	data-fragment-graph-dataset="projectedSeries"
>
	Show projection
</span>
```

`window.marketShareChart` must provide a `data.datasets` array and an `update()`
method. `window.projectedSeries` must be the dataset object to insert. When the
fragment is shown, the plugin appends that object and updates the chart. When it
is hidden, the plugin removes only the insertion owned by that fragment and
updates again. The historical one-based
`data-fragment-graph-dataset-location` attribute is maintained while the
insertion is active.

Legacy Presenter loads the script through the `RevealChartjs` WordPress script
handle and passes its global plugin object to Reveal. Native Presenter loads the
same object, registers it as the `chartjs` Presenter Reveal plugin before
initialization, and includes that ID in the native Reveal configuration. The
authored graph and dataset global contract is identical in both runtimes.

Inline Chart.js CDN markup and historical Google Charts markup found in old
decks are separate concerns; this plugin neither supplies nor rewrites those
external libraries.
