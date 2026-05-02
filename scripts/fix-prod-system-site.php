<?php
/**
 * @file
 * Defensive post-deploy fix for `system.site` config.
 *
 * Runs after `drush cim` to repair values that get clobbered when someone
 * exports config from a local environment without sanitizing it first:
 *
 *  - `system.site.name` was getting overwritten with "jalvarez.tech (local)".
 *  - `system.site.mail` was getting overwritten with "admin@example.com".
 *  - `system.site.page.front` pointed at `/page/8` (a canvas_page id from a
 *    local snapshot that doesn't exist on prod), which produced site-wide
 *    404s on `/`, `/es`, `/en` until manually fixed.
 *
 * Resolution strategy:
 *
 *  - name + mail are forced to canonical strings.
 *  - front is resolved DYNAMICALLY by loading the canvas_page titled "Inicio"
 *    and using its canonical internal path (`/page/{id}`). This avoids the
 *    cross-environment ID-mismatch problem — Inicio is id=1 in prod but may
 *    be id=8+ in any given local DB.
 *
 * Idempotent: only writes when values differ from the canonical truth.
 *
 * Run manually:
 *   drush php:script scripts/fix-prod-system-site.php
 *
 * Run automatically: triggered by .github/workflows/deploy.yml AFTER `drush cim`.
 */

$canonical_name  = 'jalvarez.tech';
$canonical_mail  = 'stevanswd@gmail.com';
$home_alias      = '/inicio';   // ES alias of the home canvas_page (seed-defined).

// 1. Resolve the home canvas_page by its public path alias.
//
// Why alias and not title? Canvas_page titles drift between snapshots
// (currently "Inicio (Canvas)" in prod, but the suffix is implementation
// trivia). The path alias is semantic + stable: it's the URL the editor
// chose, exported to the entity's `path` field by the seed scripts.
//
// path_alias.manager resolves '/inicio' → '/page/{id}' regardless of which
// numeric ID the home happens to have on this environment.
$path_manager = \Drupal::service('path_alias.manager');
$home_path = $path_manager->getPathByAlias($home_alias);

if ($home_path === $home_alias) {
  // No alias matched — getPathByAlias returns the input untouched on miss.
  echo "✗ Could not resolve alias '{$home_alias}' to an internal path.\n";
  echo "  (system.site.name and .mail will still be repaired below.)\n";
  $home_path = NULL;
}
elseif (!preg_match('#^/page/\d+$#', $home_path)) {
  echo "✗ Alias '{$home_alias}' resolved to '{$home_path}' which is not a /page/{id} path.\n";
  echo "  Refusing to set as front (canvas_page entities use /page/{id} as canonical).\n";
  $home_path = NULL;
}

// 2. Compare current vs canonical and write only on drift.
$config = \Drupal::configFactory()->getEditable('system.site');
$changes = [];

if ($config->get('name') !== $canonical_name) {
  $config->set('name', $canonical_name);
  $changes[] = "name → '{$canonical_name}'";
}

if ($config->get('mail') !== $canonical_mail) {
  $config->set('mail', $canonical_mail);
  $changes[] = "mail → '{$canonical_mail}'";
}

if ($home_path !== NULL && $config->get('page.front') !== $home_path) {
  $config->set('page.front', $home_path);
  $changes[] = "page.front → '{$home_path}' (resolved from alias '{$home_alias}')";
}

if ($changes) {
  $config->save();
  echo "✓ system.site repaired:\n";
  foreach ($changes as $c) {
    echo "  - {$c}\n";
  }
  // Caches need to clear so the new front page resolves immediately.
  drupal_flush_all_caches();
  echo "✓ caches flushed\n";
}
else {
  echo "= system.site already canonical, nothing to do.\n";
}

// 3. Repair update.settings notification email (also leaks from local cex).
$update_config = \Drupal::configFactory()->getEditable('update.settings');
$current_emails = $update_config->get('notification.emails') ?? [];
if (!in_array($canonical_mail, $current_emails, TRUE) || in_array('admin@example.com', $current_emails, TRUE)) {
  $update_config->set('notification.emails', [$canonical_mail])->save();
  echo "✓ update.settings.notification.emails → ['{$canonical_mail}']\n";
}
