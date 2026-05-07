# scripts/maintenance/

On-demand PHP scripts that survived the PR2/PR3c cleanup. These run via
`drush php:script` over SSH (see `docs/DEPLOYMENT.md` § Cómo correr un
script puntual en prod).

> **Update PR3c (2026-05-07):** the `setup/` subfolder was deleted after
> auditing each script against `config/sync/`. All 11 setup scripts
> were 100% reproducible from the active config (10 COVERED + 1
> OBSOLETE — `configure-page-displays.php` still targeted
> `node.page.field_canvas`, which the architecture migrated away from
> when canvas_page became its own entity). A clean reinstall now uses
> `drush si --existing-config` and that's it.

Anything reusable enough to deserve a flag-driven CLI lives as a Drush
command in `web/modules/custom/jalvarez_site/src/Drush/Commands/` instead
(`MaintenanceCommands` for audits / rebuilds, `RepairCommands` for the
deploy-time repairs). Use the scripts here for the cases below — they
handle one-off mutations that don't justify the ceremony of a Drush
command but still need to live in the repo because they encode tribal
knowledge about specific bugs we hit.

## Scripts

### `canvas-discover-sdcs.php`

Re-registers every `byte:*` SDC as a Canvas Component config entity.
Run after adding a new SDC under `web/themes/custom/byte/components/` if
`drush cim` didn't pick it up automatically (it usually does, but the
pipeline has gaps when component metadata changes mid-flight).

### `restore-canvas-home-en.php`

Emergency-restores the EN translation of the Inicio canvas_page when the
Canvas 1.3.x translation-wipe bug strikes. The
`CanvasPageHook::presave` defence in `jalvarez_site.module` catches this
99% of the time — keep this script around for the 1%. Idempotent.

Validate the guard programmatically with
`drush jalvarez:test-wipe-guard` (faster than running this script).

### `regenerate-canvas-blocks.php`

Re-registers our custom Block plugins (`jalvarez_projects_grid`,
`jalvarez_notes_grid`, etc.) as Canvas Component config entities. Run
after editing a block plugin's `defaultConfiguration()` or schema, since
Canvas snapshots these into config and won't pick up runtime changes
otherwise.

### `list-canvas-page-tree.php`

Pretty-prints the `components` field of a canvas_page so you can verify
ES vs EN parity without firing up the editor. Used as a sanity check
when a translation-wipe report comes in.

### `migrate-main-menu.php`

Seeds the four `system.menu.main` links (Inicio · Proyectos · Notas ·
Contacto) and their EN translations (home · work · writing · contact)
as `menu_link_content` entities. Idempotent — looks up by stable UUID
so a re-run updates instead of duplicating. Required after a fresh
`drush si --existing-config` since menu_link_content rows are *content*
(not config) and don't ship with `config/sync/`.

### `migrate-footer-menus.php`

Seeds the 9 footer link items across the 3 footer menus:

- `system.menu.footer` — Navegación column (4 items, ES+EN translations)
- `system.menu.footer_contact` — Contacto column (3 items, no translation needed)
- `system.menu.footer_social` — En otros lugares column (2 items)

Same idempotency contract as `migrate-main-menu.php`. Run after a fresh
reinstall to repopulate the footer.

## When to graduate a script to a Drush command

If you find yourself running one of these from prod more than twice a
quarter, port it to `MaintenanceCommands.php`. The Drush command form
gets DI, `drush help` discoverability, attribute routing and PHPStan
type-checking — none of which apply to scripts in this folder.
