# Release QA Checklist

Use this checklist before treating a tagged build as stable.

## Source Checks

- Confirm the working tree is clean.
- Confirm plugin header version, `SBM_VERSION`, `readme.txt` stable tag, and `CHANGELOG.md` all match.
- Run:

```bash
php tools/quality-check.php
git diff --check
```

- Run WordPress Plugin Check against the development checkout and review any runtime `src/` findings.
- Confirm development-only Plugin Check findings are covered by `.distignore`.

## Release Package

- Build the installable zip from the tagged source using `.distignore`.
- Inspect the zip contents and confirm these are absent:
  - `.git`, `.github`, `.gitignore`, `.distignore`
  - `tests`, `tools`, `vendor`, `node_modules`
  - `phpcs.xml.dist`, `phpunit.xml.dist`
  - local planning files such as `AI.md` and `reports-plan.md`
  - `.DS_Store` and `.gitkeep`
- Confirm the plugin header inside the zip reports the intended version.

## Staging Install

- Install the release zip on a staging site.
- Activate the plugin and confirm there are no PHP fatals or visible admin errors.
- Run WordPress Plugin Check against the packaged install.
- Confirm the Studio Booking dashboard loads.
- Smoke test:
  - Create and edit a person.
  - Create and edit a location.
  - Create and edit an access record.
  - Create and edit a booking.
  - Check in and check out a visit.
  - Open QR Check-in and resolve a token or manual lookup.
  - Export CSV data from reports and import/export tools.
  - If WooCommerce is active, open product access mapping and Commerce status.
  - If Google Calendar sync is configured, create or update a test booking and verify sync status.

## Production Rollout

- Confirm a current production backup exists.
- Install the release zip.
- Activate or update the plugin.
- Repeat the short smoke test for the workflows used by the site.
- Watch PHP error logs, scheduled actions, and staff-reported issues after launch.

## Post Release

- Publish or update the GitHub Release asset.
- Record any staging or production follow-up bugs for the next patch version.
