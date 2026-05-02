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

use Drupal\canvas\Entity\Page;

$canonical_name  = 'jalvarez.tech';
$canonical_mail  = 'stevanswd@gmail.com';
$home_title      = 'Inicio';

// 1. Resolve the home canvas_page by title (portable across envs).
$ids = \Drupal::entityQuery('canvas_page')
  ->condition('title', $home_title)
  ->accessCheck(FALSE)
  ->range(0, 1)
  ->execute();

if (empty($ids)) {
  echo "✗ Could not find canvas_page with title '{$home_title}'. Aborting front-page fix.\n";
  echo "  (system.site.name and .mail will still be repaired below.)\n";
  $home_path = NULL;
}
else {
  $home_id = (int) reset($ids);
  /** @var \Drupal\canvas\Entity\Page $home */
  $home = Page::load($home_id);
  if (!$home) {
    echo "✗ canvas_page id={$home_id} could not be loaded. Aborting front-page fix.\n";
    $home_path = NULL;
  }
  else {
    // Canonical internal path is /page/{id} per canvas_page entity links.
    $home_path = '/page/' . $home_id;
  }
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
  $changes[] = "page.front → '{$home_path}' (canvas_page '{$home_title}')";
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
