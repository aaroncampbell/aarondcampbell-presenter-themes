# Contributing

This repository is a private, site-specific companion to Presenter. Keep its
release and packaging lifecycle separate from the public Presenter plugin.

## Standards

-   Support WordPress 7.0 or later and PHP 8.3 or later.
-   Use Node.js 24 and npm 11 for JavaScript and theme CSS checks.
-   Follow WordPress Core, Documentation, and Extra coding standards.
-   Keep public hooks backward-compatible unless a change is explicitly planned.
-   Add focused tests for behavior changes and avoid persistent database writes in
    registration or rendering callbacks.
-   Run `npm run build:css` after changing theme Sass and commit the generated CSS
    with its source. `npm run check` rejects stale generated CSS without modifying
    the working tree.
-   Keep the Chart integration dependency-free. It adapts authored global chart
    and dataset objects to Reveal fragments; it does not bundle Chart.js.
-   Preserve both integration paths: the legacy `RevealChartjs` global and the
    native Presenter plugin ID `chartjs`.

## Before committing

Run the local quality suite:

```sh
composer check
composer phpcs
composer check:audit
npm ci
npm run check
npm run check:audit
```

Release archives must be built from a clean committed tree with
`npm run package`; never distribute a repository source archive directly.

JavaScript behavior tests use Node's built-in `node:test` runner. Changes to
`js/chartjs-plugin.js` should add or update focused cases under `tests/js` for
listener registration, fragment show/hide behavior, repeat safety, malformed
global references, and legacy/native registration. No browser DOM shim or chart
library is required: use small Reveal, chart, and dataset test doubles.

Theme CSS uses the exact Dart Sass compatibility version recorded in the npm
lockfile. Do not update Sass or migrate deprecated Sass syntax without a separate
review of the generated CSS and representative slides. Source maps are not
generated because release packages intentionally exclude the Sass source.

Then run the companion integration suite from the adjacent Presenter repository.
Its ignored `.wp-env.override.json` must mount this sibling checkout as described
in Presenter's local-development documentation:

```sh
npx wp-env run tests-cli --env-cwd=wp-content/plugins/aarondcampbell-presenter-themes -- vendor/bin/phpunit --configuration=phpunit.xml.dist
```

Presenter changes that affect the companion contract must also pass:

```sh
npm run test:php
```
