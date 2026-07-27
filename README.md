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

Install the pinned Node development dependencies and run the theme CSS and
shipped JavaScript checks:

```sh
npm ci
npm run check
npm run check:audit
```

`npm run check` verifies that the committed theme CSS matches its Sass source,
runs `node --check` against the production Chart integration and development
tools, and runs the focused `node:test` suite. CI applies WordPress JavaScript
lint and formatting standards through Presenter's pinned development
toolchain. The JavaScript is shipped directly; there is no bundler or generated
JavaScript output.

The theme CSS is generated from the Sass sources with the exact Dart Sass and
Reveal.js versions in `package-lock.json`:

```sh
npm run build:css
npm run check:css
```

Commit the generated CSS with its source changes. Source maps are intentionally
disabled: release packages exclude the Sass source, so shipping maps would not
provide usable debugging context. The compiler resolves Reveal's theme modules
from the pinned `reveal.js` development dependency, keeping the custom themes
on the same theme API as Presenter without copying upstream template files.

The plugin ships three themes:

- `aaron`, the original dark Aaron theme, ported to Reveal.js 6
- `aaron-purple`, the existing site default, ported to Reveal.js 6 while
  preserving its presentation-specific utilities
- `aaron-brand`, the current brand-system variant using the approved Poppins
  and Open Sans fonts, semantic colors, pattern, and fedora artwork from the
  adjacent `aaron-brand` repository

Aaron Purple remains the default so existing presentations do not change
themes implicitly. Aaron Brand is available as a separate stable theme ID.

## AaronDCampbell.com local site

The local site's Compose configuration bind-mounts this checkout and the
adjacent Presenter checkout over the plugin copies in its persistent
`wp-content` volume. To update both plugins after pulling:

```sh
npm --prefix ../presenter ci
npm --prefix ../presenter run build
npm ci
npm run build
docker compose -f ../local-site/compose.yaml up -d
```

The first install may be slow while npm populates its cache. Later builds use
the same mounted paths; no plugin copying or WordPress volume reset is needed.

The integration tests use Presenter's shared `wp-env` WordPress installation.
In the adjacent `presenter` repository, copy `.wp-env.override.example.json` to
the ignored `.wp-env.override.json` so the local companion checkout is mounted.
Then start the environment and run:

```sh
npm run env:start
npx wp-env run tests-cli --env-cwd=wp-content/plugins/aarondcampbell-presenter-themes -- vendor/bin/phpunit --configuration=phpunit.xml.dist
```

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
