<?php
/**
 * @file
 * Copy the `portrait` prop from the ES banner-inicio component into the
 * EN translation's banner-inicio component, on the home canvas_page.
 *
 * Why: Canvas 1.3.x's visual editor only edits the default translation
 * (ES on this site). When you attach a Media image to the portrait field
 * via the editor, only ES gets it — EN stays empty. This script pushes the
 * same Media reference into the EN tree so the same image renders on /en.
 *
 * Idempotent. Safe to re-run after re-attaching a different image in ES.
 *
 * Usage:
 *   ddev exec ./web/vendor/bin/drush php:script scripts/sync-portrait-en-from-es.php
 */

use Drupal\canvas\Entity\Page;

// Resolve the home page by its ES alias (portable across envs).
$home_path = \Drupal::service('path_alias.manager')->getPathByAlias('/inicio');
if (!preg_match('#^/page/(\d+)$#', $home_path, $m)) {
  fwrite(STDERR, "✗ Could not resolve '/inicio' to a canvas_page.\n");
  exit(1);
}
$page = Page::load((int) $m[1]);
if (!$page->hasTranslation('en')) {
  fwrite(STDERR, "✗ Page {$page->id()} has no EN translation.\n");
  exit(1);
}

// Pull `portrait` from the ES banner-inicio.
$es_tree = $page->getTranslation('es')->get('components')->getValue();
$portrait = NULL;
foreach ($es_tree as $row) {
  if ($row['component_id'] !== 'sdc.byte.banner-inicio') continue;
  $inputs = json_decode($row['inputs'], TRUE) ?? [];
  $portrait = $inputs['portrait'] ?? NULL;
  break;
}
if (!$portrait) {
  fwrite(STDERR, "✗ ES banner-inicio has no `portrait` set. Attach the image in /es first.\n");
  exit(1);
}
echo "→ Source (ES portrait): " . json_encode($portrait) . "\n";

// Push it into the EN banner-inicio.
$en = $page->getTranslation('en');
$en_tree = $en->get('components')->getValue();
$found = FALSE;
foreach ($en_tree as $i => $row) {
  if ($row['component_id'] !== 'sdc.byte.banner-inicio') continue;
  $inputs = json_decode($row['inputs'], TRUE) ?? [];
  $had_before = $inputs['portrait'] ?? '<unset>';
  $inputs['portrait'] = $portrait;
  $en_tree[$i]['inputs'] = json_encode($inputs, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
  echo "→ Target (EN banner-inicio uuid={$row['uuid']}):\n";
  echo "    portrait was: " . (is_array($had_before) ? json_encode($had_before) : $had_before) . "\n";
  echo "    portrait now: " . json_encode($portrait) . "\n";
  $found = TRUE;
  break;
}
if (!$found) {
  fwrite(STDERR, "✗ EN tree has no banner-inicio component.\n");
  exit(1);
}

$en->set('components', $en_tree);
$en->save();
drupal_flush_all_caches();

echo "✓ EN banner-inicio portrait synced from ES. Verify at /en.\n";
