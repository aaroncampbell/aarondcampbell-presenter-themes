# Companion maintenance decisions

These decisions record deliberate compatibility choices so they are not
mistaken for unreviewed drift during future cleanup.

## Legacy footer branding

The persistent footer continues to use the historical Twitter link, bird, and
`@AaronCampbell` label. It is presentation content and branding, not a runtime
compatibility defect, and will change only with a separate branding decision.

## Licensing

Presenter remains GPL-2.0-or-later and this separately distributed private
companion remains GPL-3.0-or-later. The licenses are compatible for this use,
and the difference is intentional rather than a release-version mismatch.

## Bundled Reveal themes

Presenter retains Reveal's complete compatibility theme set because existing
decks may select any registered theme. Only one theme stylesheet loads for a
deck. The unusually large Black, White, and contrast variants contain inlined
font data from Reveal's distributed theme build; removing those files or
rewriting their font contract would be a compatibility and visual change, not
housekeeping.
