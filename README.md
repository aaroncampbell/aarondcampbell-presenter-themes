# Aaron D. Campbell Presenter Themes

This private WordPress plugin provides AaronDCampbell.com-specific themes and
presentation customizations for [Presenter](https://github.com/aaroncampbell/presenter).
It is installed and distributed separately and is never packaged with Presenter.

## Requirements

-   WordPress 7.0 or later
-   PHP 8.3 or later
-   Presenter

## Development

Install the PHP development dependencies and run the quality checks:

```sh
composer install
composer check
composer phpcs
composer check:audit
```

The integration tests use Presenter's shared `wp-env` WordPress installation.
In the adjacent `presenter` repository, copy `.wp-env.override.example.json` to
the ignored `.wp-env.override.json` so the local companion checkout is mounted.
Then start the environment and run:

```sh
npm run env:start
npx wp-env run tests-cli --env-cwd=wp-content/plugins/aarondcampbell-presenter-themes -- vendor/bin/phpunit --configuration=phpunit.xml.dist
```

The checked-in CSS and JavaScript are compatibility assets. Their modern build
pipelines are being established separately; do not regenerate them with an
unpinned local toolchain.
