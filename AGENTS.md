# Agent guidance for this Drupal site

This codebase is a Composer-managed Drupal site. Local development uses `ddev`.

## Architecture, design and deployment (read first)

- [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) — site architecture (content types, taxonomies, views, SDC, routing, i18n).
- [docs/DESIGN.md](docs/DESIGN.md) — visual design tokens, component cross-reference (CSS ↔ SDC), final ES/EN copy, page composition.
- [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md) — GitHub Actions → Hostinger pipeline. CSS is compiled in CI from SCSS sources, only built artefacts ship to the host.

Update the relevant doc in the same PR when architecture, design, or pipeline changes.

**Build/deploy invariants:**
- The repo versions **only sources**: `.scss`, `.twig`, `.json`, `.php`, etc. NO compiled CSS, NO `node_modules/`, NO `vendor/`, NO `dist/`, NO `icons.svg` sprite.
- All compilation (Composer, npm, sass, icon sprite) happens in GitHub Actions on every push to `main`.
- Hostinger receives a clean artefact with `vendor/` + compiled CSS + sprite + theme + modules. Hostinger does not run build tools.

## Local environment (DDEV)

Run commands from the project root:

- Start or restart the local environment with `ddev start`, `ddev restart`, and `ddev stop`.
- Install PHP dependencies with `ddev composer install`.
- Open the site with `ddev launch`.
- Run Drush commands with `ddev drush <command>` such as `status`, `user:login`,  `cache:rebuild`, and `update:db`.

DDEV project config lives in `.ddev/config.yaml`. Use `.ddev/config.local.yaml` for machine-specific overrides.

## Common Drupal workflows

- Add a module with `ddev composer require drupal/<project>`, then  `ddev drush pm:enable --yes <module_machine_name>`, then `ddev drush cache:rebuild`.
- Apply database updates after code changes with `ddev drush update:db --yes`.
- Import repository configuration into the site with `ddev drush config:import --yes`.
- Export site configuration back to the repo with `ddev drush config:export --yes`.

## Guardrails

- Do not commit secrets or machine-local overrides such as `.env`, `settings.local.php`, or `.ddev/config.local.yaml`.
- Do not commit `vendor/` or uploaded files under `web/sites/*/files`.
- Do not edit Drupal core or contributed projects in place.
- Put custom code in `web/modules/custom` and `web/themes/custom`.

## References

- https://docs.ddev.com/en/stable/
- https://www.drupal.org/docs/administering-a-drupal-site/configuration-management/workflow-using-drush
