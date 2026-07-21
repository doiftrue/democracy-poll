# Repository Guidelines

## Overview
This is WordPress plugin that distributed to oficial WordPress plugin repository.

## Project Structure & Module Organization
- `democracy.php` is the main plugin bootstrap; `autoload.php` and `uninstall.php` handle loading and cleanup.
- `classes/` holds most PHP logic (namespaced classes).
- `includes/` contains supporting functions and helpers.
- `admin/` contains admin UI PHP and assets.
- `assets/styles/` stores CSS themes.
- `assets/js/` stores ES modules and the compiled bundle.
- `languages/` contains translations and the POT build script.
- `tests/unit/` contains PHPUnit tests and bootstrap.
- `docs/` contains the standalone VitePress documentation. Never inspect, search, analyze, or modify this directory while developing the plugin unless the user explicitly asks to work with the documentation.
- `vendor/` and `node_modules/` are generated dependencies.
- `.distignore` lists files to exclude from the WordPress.org ZIP package.

## Build, Test, and Development Commands
- `composer install` installs PHP dev dependencies.
- Always run the PHPUnit suite with `make phpunit`, which uses the project's Docker environment. Do not check for or attempt to use a host-installed `php` first.
- `npm install` installs JS build dependencies (Node >= 24.12).
- `npm run build` bundles `assets/js/democracy.mjs` into `assets/js/democracy.min.js` (with sourcemap).
- `npm run watch` rebuilds JS on changes.
- `make i18n.update-po` regenerates `languages/democracy-poll.pot` (requires `xgettext`).
- `make i18n.make-mo-php` generates `languages/democracy-poll.mo` and `languages/democracy-poll.l10n.php` from the PO files.
- `make npm.build`, `make phpunit`, etc. use Docker helpers with local paths; adjust the Makefile before relying on them.

## Coding Style & Naming Conventions
- PHP follows WordPress conventions: tabs for indentation, `snake_case` for functions, `StudlyCaps` for classes, `UPPER_SNAKE` for constants.
- JS uses ES modules. `assets/js/democracy.mjs` is main file; `assets/js/democracy.min.js` is build artifact of main file.
- Use `snake_case` for all JavaScript `element.dataset` property names and their corresponding HTML `data-*` attributes. Keep the name identical on both sides so it can be found by exact text search without camelCase/kebab-case conversion. Example: `element.dataset.vote_check_done` corresponds to `data-vote_check_done`.
- CSS themes in `styles/` are lower-case filenames; avoid inline edits to generated assets.

## Testing Guidelines
- PHPUnit + WP_Mock are used; tests live in `tests/unit/` and must end with `Test.php`.
- Tests use `https://github.com/doiftrue/unitest-wp-copy`, a lightweight copy of the WordPress runtime that provides core WordPress functions, classes, and constants without bootstrapping a full WordPress installation. Before mocking or defining a WordPress symbol in a test, check whether it is already provided; the complete list of available symbols is documented in `vendor/doiftrue/unitest-wp-copy/wp-runtime/SYMBOLS-INFO.md`.
- `phpunit.xml` enables `forceCoversAnnotation`; add `@covers` or `@coversNothing` to new tests.
- In PHPUnit tests, place each data provider method immediately after the test method that uses it.
- Test observable behavior, logic, and return values rather than implementation details. Do not assert how many times a function is called unless the call count is part of the required behavior; safe refactoring should not break tests.
- There is no explicit coverage threshold configured.
- `classes/Infra/Container.php` - should not be covered with tests, because it third-party code that already covered by PHPUnit. 

## Commit & Pull Request Guidelines
- Do not commit changes automatically. Leave all changes uncommitted so the user can review and commit them manually.
- Commit messages typically use short prefixes like `IMP:`, `FIX:`, `NEW:`, `CHG:` or version tags like `v6.1.0`.
- PRs should describe the user impact and include screenshots/GIFs for admin UI changes.

## Release Process
- Releases are deployed to the WordPress.org plugin repository by `.github/workflows/deploy.yml` using `10up/action-wordpress-plugin-deploy@stable` when a Git tag is pushed to GitHub.
  - Do not commit to WordPress.org SVN manually. Prepare the release in Git, then create and push a `vX.Y.Z` tag; GitHub Actions handles the SVN deployment and ZIP generation. Do not add tag - I will do it myself.
- DO not touch headers in `readme.txt` and main `democracy.php` files. I will support it myself.
- Check that readme.txt has current version changelog in `== Changelog ==` section and add (write) it if not exits.

## Browser Support & Compatibility
- Target modern browsers with ES module support; No IE11 support.

## External Services
- IP geolocation uses the free `https://ipwho.is/` endpoint. It is limited to 60 requests per minute per client IP and is licensed for non-commercial use only. See https://ipwhois.io/documentation.

## Additional notes
- Do not exmine file `democracy.min.js` in most cases - it will be regenerated manually on tests and on deployment (release).
