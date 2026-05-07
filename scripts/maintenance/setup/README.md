# scripts/maintenance/setup/ — pending audit

These 11 scripts created the initial structural skeleton of the site
(content types, fields, paragraph types, taxonomies, views, displays,
block placements, sitemap variant config). They were the very first
batch run against an empty Drupal install during F1–F3 of the project.

## ⚠️ Why they're parked here, not deleted

In theory, the structures these scripts produce should now live in
`config/sync/` and reproduce automatically on a fresh `drush deploy +
cim`. **In practice we haven't audited that claim yet.** The safe move
is to keep them around until someone validates that:

1. `drush sql:drop -y && drush si --existing-config -y` leaves the site
   in a state functionally equivalent to production.
2. Every entity type, field, view, paragraph type, display, block
   placement and sitemap-variant config they create is present under
   `config/sync/`.
3. The recipe `recipes/jalvarez_setup/` (PR3 deliverable) covers
   anything `cim` legitimately can't handle.

Once that audit passes, this whole folder can go away. Until then,
treat the scripts as a fallback for catastrophic recovery.

## Scripts

| Script | What it creates |
|---|---|
| `build-content-model.php` | 3 vocabularies, 4 content types, 3 paragraph types, ~50 fields. |
| `build-views.php` | 7 views (projects + notes lists, featured, related, etc.). |
| `build-phase-g.php` | Webform options, contact webform, pathauto patterns. |
| `configure-form-displays.php` | Form displays with field_group fieldsets for project/note/page. |
| `configure-page-displays.php` | Form/view display for `node.page` with the canvas widget. |
| `configure-simple-sitemap.php` | Registers canvas_page + node:project + node:note as indexable bundles in the `default` sitemap variant. |
| `create-media-image-type.php` | `media_type: image` with `field_media_image` (required by canvas_page). |
| `place-nav-block.php` | Places `byte_navglass` in the header region. |
| `add-notes-block-display.php` | Adds `block_1` display to the notes view. |
| `disable-view-page.php` | Removes `page_1` displays from projects + notes views. |
| `cleanup-default-blocks.php` | Disables the 5 default byte blocks (branding, admin, etc.) we don't want. |

## How to run any of these

```bash
SCRIPT=scripts/maintenance/setup/<name>.php
scp "$SCRIPT" hostinger:/tmp/
ssh hostinger "cd \$DRUPAL_PATH/public_html && \
  ./vendor/bin/drush php:script /tmp/$(basename "$SCRIPT") && \
  rm /tmp/$(basename "$SCRIPT")"
```

All scripts here are designed to be idempotent (they check whether the
target already exists before mutating) so re-running on an environment
that's already partially set up is safe.

## Audit checklist (when you tackle the cleanup)

- [ ] `composer drush:deploy` from a clean DB recreates everything
- [ ] `drush config:diff` after the deploy is clean
- [ ] `recipes/jalvarez_setup/` covers anything `cim` can't reproduce
- [ ] Delete this folder + this README
- [ ] Update `docs/DEPLOYMENT.md` § Post-deploy específico de Canvas
