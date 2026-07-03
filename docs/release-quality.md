# Release and Quality Checks

Use this checklist before opening a release PR or packaging a plugin zip.

## Local Quality Command

Run the dependency-free quality command:

```bash
php tools/quality-check.php
```

This command checks PHP syntax, runs the lightweight access-rule test suite, and verifies release packaging ignores.

When Composer dev dependencies are installed, the equivalent Composer command is:

```bash
composer quality
```

## Optional Standards Tools

The repository includes `phpcs.xml.dist` and `phpunit.xml.dist` so a fuller local environment can run:

```bash
vendor/bin/phpunit
vendor/bin/phpcs
```

WordPress Plugin Check should also be run before release from a WordPress admin environment. Record any remaining actionable findings in the PR.

## Packaging

`.distignore` excludes development-only files such as tests, tools, VCS metadata, dependency directories, and local planning files. Confirm the release zip contains the plugin runtime files, assets, docs, license, readme files, and uninstall file.

## Security Pass

For each feature PR, review:

- Capability checks before privileged actions.
- Nonces for admin POST/GET mutations.
- Sanitization for input.
- Escaping for output.
- Prepared statements for database queries.
