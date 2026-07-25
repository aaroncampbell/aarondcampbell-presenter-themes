# Contributing

This repository is a private, site-specific companion to Presenter. Keep its
release and packaging lifecycle separate from the public Presenter plugin.

## Standards

-   Support WordPress 7.0 or later and PHP 8.3 or later.
-   Follow WordPress Core, Documentation, and Extra coding standards.
-   Keep public hooks backward-compatible unless a change is explicitly planned.
-   Add focused tests for behavior changes and avoid persistent database writes in
    registration or rendering callbacks.
-   Preserve generated theme and script assets unless the change also establishes
    and verifies the corresponding deterministic build.

## Before committing

Run the local quality suite:

```sh
composer check
composer phpcs
composer check:audit
```

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
