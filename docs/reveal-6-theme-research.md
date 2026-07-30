# Reveal.js 4.x to 6.x theme research

Research date: 2026-07-19

Implementation status (2026-07-26): the companion plugin now pins Reveal.js
6.0.1 as a development dependency and compiles `aaron`, `aaron-purple`, and the
new `aaron-brand` theme with the Reveal 6 Sass module API. The copied Reveal 4
template files have been removed from the migrated themes. Aaron Purple remains
the default; Aaron Brand is registered separately under the stable
`aaron-brand` ID.

Dependency/tooling decisions made during implementation:

- Dart Sass was updated from 1.78.0 to 1.102.0 and Reveal.js 6.0.1 is now a
  direct, exact development dependency. Both npm and Composer lockfiles audit
  cleanly.
- The PHP quality stack was already current on its compatible major versions.
  PHPCS 4 is not currently installable with WPCS 3.4 and
  PHPCompatibilityWP 2.1.8, so PHPCS remains on the latest compatible 3.x
  release. PHPUnit remains on the maintained 9.6 line used by the shared
  WordPress test harness rather than changing the harness as part of a theme
  migration.
- The local Compose site bind-mounts both plugin checkouts. A disposable native
  deck confirmed that Presenter served Reveal.js 6.0.1 and the registered
  `aaron-brand` stylesheet from those mounts.
- Migrated custom HTML can contain asynchronous chart loaders. Google Charts
  may call back before Reveal marks a hash-selected slide present, when that
  slide has zero measurable dimensions; its renderer then falls back to
  400x200 even if the authored container is 800x400. The shared theme keeps
  only slides containing the historical Google Charts loader measurable until
  Reveal is ready, eliminating this theme-load timing race. Regression checks
  should compare the generated SVG and plot dimensions, not only its container.

## Scope and baseline

This note compares the theme system shipped in Reveal.js 4.1.3, 4.3.1, and
6.0.1, then maps the findings to `aaron-purple`. Reveal.js 6.0.1 is the latest
published 6.x release at the time of this research.

The comparison used the source files from the official npm packages, especially
`css/theme`, `css/theme/template`, `css/reveal.scss`, and `package.json`.

Important baseline findings:

- Reveal.js 4.1.3 and 4.3.1 have identical `css/theme/template` directories.
  There is no theme-template migration between those two versions.
- Reveal.js 6.0.0 and 6.0.1 have identical theme source directories. The
  generated theme CSS differs only in its version banner.
- `aaron-purple/scss/settings.scss`, `theme.scss`, and `exposer.scss` match the
  4.1.3/4.3.1 upstream templates. The theme is therefore firmly based on the
  Reveal.js 4.x theme API.
- The compiled CSS can probably continue to style ordinary slides under 6.x,
  because the core selectors and existing `--r-*` properties remain broadly
  compatible. The Sass source cannot simply be dropped into the 6.x build,
  however; its theme authoring API changed substantially.

## Executive summary

The visual theme template changed only modestly. The significant migration is
in how a theme is authored and built:

| Area | Reveal.js 4.1/4.3 | Reveal.js 6.0/6.0.1 | Impact on `aaron-purple` |
| --- | --- | --- | --- |
| Sass loading | Legacy `@import` | Module system with `@use` | Rewrite the entry point and imports |
| Setting names | camelCase, e.g. `$mainFont` | kebab-case, e.g. `$main-font` | Map every upstream setting override |
| Defaults | Global variables assigned normally | Module variables declared with `!default` and configured with `@use ... with (...)` | Overrides must be supplied when the settings module is loaded |
| CSS custom properties | Separate `exposer.scss` | Emitted directly by `settings.scss` | Remove the copied exposer file/import |
| Background customization | Override `bodyBackground()` mixin | Configure `$background` and `$background-color` | Convert the pattern-image mixin to settings |
| Sass color helpers | Global `lighten()`/`darken()` | `sass:color` and `color.scale()` | Replace deprecated helpers in upstream-derived code |
| Utility mixins | Gradient, transform, and light/dark-background mixins | Only light/dark-background text-color mixins remain | Preserve any locally used removed mixins or replace them with native CSS |
| Theme source location | `css/theme/source/*.scss` | `css/theme/*.scss` | Update any build/copy assumptions |
| Theme build | Gulp; `npm run build -- css-themes` | Vite; `npm run build:styles` | Add or update a reproducible local build process |
| Package import | Commonly `dist/theme/<name>.css` | npm export supports `reveal.js/theme/<name>.css`; direct distribution files remain under `dist/theme` | The plugin's own CSS URL does not have to change |
| Built-in themes | 11 themes | 14 themes | `dracula`, `black-contrast`, and `white-contrast` are new reference implementations |

Reveal.js 6 also introduces UI that did not exist in 4.x, particularly overlay
and scroll-view UI. A migrated theme should explicitly test these features even
though most of their structural styling lives in the core `reveal.css` rather
than in the theme.

## Sass theme API changes

### Entry-point structure

A typical 4.x theme follows this order:

```scss
@import "../template/mixins";
@import "../template/settings";

// Override global variables here.
$backgroundColor: #191919;
$mainFont: "Source Sans Pro", sans-serif;

@import "../template/theme";
```

A 6.x theme configures the settings module as it is loaded:

```scss
@use "sass:color";
@use "template/mixins" as mixins;
@use "template/settings" with (
  $background-color: #191919,
  $main-font: '"Source Sans Pro", sans-serif'
);
@use "template/theme";
```

This is not a cosmetic syntax update. Sass modules are loaded once, variables
are namespaced, and a module's configurable values must be supplied on its
first `@use`. For the future migration, define reusable Aaron Purple palette
values before `@use "template/settings"`, pass the relevant values through its
`with (...)` block, and retain those local palette values for custom rules.

### Upstream setting mapping

| Reveal.js 4.x | Reveal.js 6.x |
| --- | --- |
| `$backgroundColor` | `$background-color` |
| `bodyBackground()` | `$background` plus `$background-color` |
| `$mainFont` | `$main-font` |
| `$mainFontSize` | `$main-font-size` |
| `$mainColor` | `$main-color` |
| `$blockMargin` | `$block-margin` |
| `$headingMargin` | `$heading-margin` |
| `$headingFont` | `$heading-font` |
| `$headingColor` | `$heading-color` |
| `$headingLineHeight` | `$heading-line-height` |
| `$headingLetterSpacing` | `$heading-letter-spacing` |
| `$headingTextTransform` | `$heading-text-transform` |
| `$headingTextShadow` | `$heading-text-shadow` |
| `$headingFontWeight` | `$heading-font-weight` |
| `$heading1TextShadow` | `$heading1-text-shadow` |
| `$heading1Size` | `$heading1-size` |
| `$heading2Size` | `$heading2-size` |
| `$heading3Size` | `$heading3-size` |
| `$heading4Size` | `$heading4-size` |
| `$codeFont` | `$code-font` |
| `$linkColor` | `$link-color` |
| `$linkColorHover` | `$link-color-hover` |
| Implicit `darken($linkColor, 15%)` in `exposer.scss` | `$link-color-dark` |
| `$selectionBackgroundColor` | `$selection-background-color` |
| `$selectionColor` | `$selection-color` |
| Not available | `$overlay-element-bg-color` |
| Not available | `$overlay-element-fg-color` |

Reveal.js 6 also changes some defaults. Most are irrelevant because Aaron
Purple already overrides them, but the default selection background changes
from `#FF5E99` to `#0fadbb`. The new overlay color values are comma-separated
RGB channels because core styles use them inside `rgba(...)`.

### Background handling

In 4.x, `theme.scss` calls `bodyBackground()` on `.reveal-viewport` and then
sets `background-color`. Aaron Purple overrides the mixin to add
`images/asanoha-400px.png`.

In 6.x, the template instead emits:

```scss
.reveal-viewport {
  background: var(--r-background);
  background-color: var(--r-background-color);
}
```

The future equivalent should configure both the pattern and its fallback color,
approximately:

```scss
@use "template/settings" with (
  $background: url("images/asanoha-400px.png"),
  $background-color: #d6d1f7
);
```

The final relative URL must be verified from the location of the compiled
`aaron-purple.css`, not merely from the Sass source directory.

### CSS custom properties

Reveal.js 4.x has a dedicated `template/exposer.scss`, imported by
`template/theme.scss`, which turns Sass settings into 23 `--r-*` custom
properties. Reveal.js 6 removes `exposer.scss`; `settings.scss` now emits the
properties directly and adds:

- `--r-background`, distinct from `--r-background-color`
- `--r-overlay-element-bg-color`
- `--r-overlay-element-fg-color`

Core Reveal.js 6 CSS defines additional UI custom properties such as
`--r-controls-spacing`, overlay dimensions, and scroll-bar dimensions. Those
are core layout variables, not settings exposed by the theme template. They can
still be overridden in a custom theme if the design needs it.

The Dracula theme demonstrates an optional extension pattern: it defines custom
properties for bold, italic, inline-code, and list-marker colors, then adds its
own selectors. These are Dracula-specific conventions, not required Reveal.js
theme variables.

### Template selector changes

The 4.3.1 and 6.0.1 `template/theme.scss` files remain close. Functional
changes are limited:

- `.reveal-viewport` uses `--r-background` instead of the removed
  `bodyBackground()` mixin.
- The exposer import is removed because settings now expose the properties.
- Most other changes are formatting: normalized spacing, quote style, and
  unnested formatting for blockquote/link rules.
- Existing styles for headings, lists, blockquotes, code, tables, links,
  `.r-frame`, controls, progress, and print backgrounds remain.

This means the primary risk is the Sass API and build, not a wholesale change
to the generated theme selector model.

## Built-in theme inventory

Reveal.js 4.3.1 ships these 11 themes:

`beige`, `black`, `blood`, `league`, `moon`, `night`, `serif`, `simple`, `sky`,
`solarized`, and `white`.

Reveal.js 6.0.1 retains all 11 and adds:

- `dracula`, an example of more extensive theme-specific element styling
- `black-contrast`
- `white-contrast`

The contrast variants are useful references when auditing Aaron Purple for
legibility and accessible color choices. They do not create an automatic
requirement for a separate Aaron Purple contrast theme.

## `aaron-purple`-specific findings

### Required migration work

1. Replace the copied 4.x `mixins.scss`, `settings.scss`, `exposer.scss`, and
   `theme.scss` with the 6.x template approach. Avoid continuing to maintain
   copied upstream template files if the eventual build can import Reveal.js as
   a dependency.
2. Rewrite `aaron-purple.scss` to use `@use`, configure kebab-case settings in
   a `with (...)` block, and namespace any retained mixins.
3. Convert the `bodyBackground()` override to `$background` and
   `$background-color`.
4. Replace references to upstream camelCase values inside custom rules. The
   current custom code uses `$backgroundColor`, `$headingColor`, `$linkColor`,
   and `$linkColorHover`.
5. Replace the legacy global color helpers used by upstream-derived code. In
   particular, the 4.x exposer uses `darken()`; 6.x defines
   `$link-color-dark` with `color.scale()`.
6. Preserve or replace the custom transform/rotation mixins used by
   `.rotate-twitter`. Reveal.js 6 removes upstream gradient helpers, and its
   utility file contains only the light/dark slide-background text mixins.
7. Decide whether syntax-highlight styling remains embedded in the theme.
   Reveal.js documentation treats the highlight theme as a separate stylesheet
   loaded with the Highlight plugin. Keeping the embedded `highlight.scss` is
   valid, but it should be tested against Reveal.js 6's bundled Highlight plugin
   and must not be combined accidentally with a competing highlight theme.

### Existing source issue to resolve during migration

`aaron-purple.scss` imports `theme.scss` twice: once before the custom selector
block and once after it. Under normal Sass `@import` semantics, rebuilding this
entry point would duplicate the base Reveal theme rules and make cascade
behavior harder to reason about. The checked-in CSS contains only one
`.reveal-viewport` selector, so it may not have been generated from the exact
current Sass entry point. The 6.x rewrite should load `template/theme` exactly
once, before optional theme-specific styles, and should verify that source and
generated CSS are in sync.

### What does not need to change solely because of Reveal.js 6

- The WordPress Presenter registration can continue to point directly to
  `aaron-purple/aaron-purple.css`; Reveal's npm export paths do not dictate the
  plugin's URL.
- Custom fragment classes, grids, galleries, stamps, and persistent social-link
  rules are not part of the upstream theme API. They need regression tests, but
  not mechanical renaming.
- The theme may remain a standalone compiled CSS file. A consumer does not need
  Sass or Vite at runtime.

## Future implementation approach

When implementation begins, prefer this sequence:

1. Pin Reveal.js 6.0.1 (or the then-current intended target) as a development
   dependency so the theme compiles against a known template version.
2. Create a clean 6.x entry point using `@use` and a single
   `template/theme` load.
3. Port only Aaron Purple's design tokens and custom selectors; do not carry
   forward copied 4.x framework template code.
4. Produce the standalone `aaron-purple.css` expected by Presenter.
5. Compare the old and new theme on the same 4.x-era representative deck to
   catch unintended visual drift.
6. Add a Reveal.js 6 fixture deck that exercises new and existing UI.

## Regression test matrix for the eventual upgrade

- Basic slide typography: `h1` through `h6`, paragraphs, nested lists, links,
  quotes, tables, images, video, and iframe sizing
- Horizontal and vertical navigation, controls, progress bar, slide numbers,
  overview, pause state, and help/keyboard overlays
- Light and dark slide backgrounds, image backgrounds, and the global patterned
  viewport background
- Fragments, including every custom Aaron Purple fragment variant
- Code blocks, inline code, line numbers, highlighted lines, and long/scrolling
  code
- Auto-Animate and `.r-frame`
- Scroll view and its scroll bar
- Speaker view and print/PDF output
- Mobile/narrow viewport behavior
- Presenter-specific persistent social links, stamps, columns, galleries, and
  permalink hiding
- Color contrast for body text, headings, links and hover states, selections,
  controls, progress, overlays, code, and patterned backgrounds

## Legacy Google Chart migration

Presenter 2.0 now exposes `presenter_migration_slide_blocks` before its
lossless Custom HTML fallback. This companion plugin uses that hook to inspect
an entire legacy slide and safely join detached Google Chart scripts to their
container elements through literal `document.getElementById()` targets.

The converter accepts only the characterized line-chart grammar used by the
legacy deck corpus: literal `arrayToDataTable()` arrays, `new Date()` values,
simple numeric multiplication, literal options objects, and a literal target
ID. It never evaluates legacy JavaScript. Every chart script and target must be
accounted for or conversion fails closed and Presenter retains the complete
Custom HTML slide.

Successful conversion emits ordinary heading and paragraph blocks plus native
`presenter/chart` blocks. The chart block bundles Chart.js through Presenter's
front-end build, renders an accessible data table, initializes when its Reveal
slide becomes current, and derives canvas colors from semantic CSS custom
properties with theme-neutral fallbacks. Known hard-coded legacy theme colors
are removed during conversion. Slide 15 of legacy deck 1952 is the reference
fixture: it produces two 800-by-400 chart blocks while retaining fragment index
1 and the `swap-out block` / `swap-in block` custom fragment classes.

## Official references

- [Reveal.js themes documentation](https://revealjs.com/themes/)
- [Reveal.js upgrade instructions](https://revealjs.com/upgrading/)
- [Reveal.js installation and npm import paths](https://revealjs.com/installation/)
- [Reveal.js code/highlight theme documentation](https://revealjs.com/code/)
- [Reveal.js 4.1.3 theme template](https://github.com/hakimel/reveal.js/tree/4.1.3/css/theme/template)
- [Reveal.js 4.3.1 theme template](https://github.com/hakimel/reveal.js/tree/4.3.1/css/theme/template)
- [Reveal.js 6.0.1 theme sources](https://github.com/hakimel/reveal.js/tree/6.0.1/css/theme)
- [Reveal.js 6.0.1 package](https://www.npmjs.com/package/reveal.js/v/6.0.1)
