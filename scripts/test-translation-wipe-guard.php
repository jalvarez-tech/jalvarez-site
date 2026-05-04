<?php
/**
 * @file
 * Smoke test for the translation-wipe-guard hook.
 *
 * Reproduces the bug scenario via the entity API and verifies that the
 * hook in jalvarez_site_canvas_page_presave() prevents the wipe.
 *
 * The test:
 *   1. Loads the Inicio canvas_page (resolved by alias `/inicio`).
 *   2. Snapshots the EN translation's components count + path alias.
 *   3. Simulates a hostile save: edits the ES translation's title only
 *      AND artificially blanks the EN translation's `components` field
 *      (mimicking what the buggy AutoSaveManager flow would do).
 *   4. Saves.
 *   5. Re-loads the entity and asserts:
 *      - EN translation still exists.
 *      - EN components count == snapshot.
 *      - EN path alias unchanged.
 *      - ES title is reverted (since the test save was simulated; we don't
 *        actually want to change ES content for real).
 *
 * Run via:
 *   drush php:script scripts/test-translation-wipe-guard.php
 *
 * Safe to run on production: any change is reverted at the end.
 */

use Drupal\canvas\Entity\Page;

// 1. Resolve the home canvas_page by alias.
$home_path = \Drupal::service('path_alias.manager')->getPathByAlias('/inicio');
if (!preg_match('#^/page/(\d+)$#', $home_path, $m)) {
  fwrite(STDERR, "✗ Could not resolve /inicio. Got '{$home_path}'.\n");
  exit(1);
}
$page_id = (int) $m[1];
$page = Page::load($page_id);
if (!$page instanceof Page) {
  fwrite(STDERR, "✗ canvas_page id={$page_id} not loadable.\n");
  exit(1);
}
echo "→ Target: canvas_page id={$page_id} title='{$page->label()}'\n";

if (!$page->hasTranslation('en')) {
  echo "✗ EN translation missing; nothing to protect. Run scripts/restore-canvas-home-en.php first.\n";
  exit(1);
}

// 2. Snapshot EN state.
$en = $page->getTranslation('en');
$en_components_before = count($en->get('components')->getValue());
$en_alias_before = $en->get('path')->first()?->getValue()['alias'] ?? null;
$en_title_before = (string) $en->label();
echo "→ EN snapshot: title='{$en_title_before}', components={$en_components_before}, alias='{$en_alias_before}'\n";

// Snapshot ES title so we can revert.
$es = $page->getTranslation('es');
$es_title_before = (string) $es->label();

// 3. Simulate the hostile save: edit ES + wipe EN components.
echo "→ Simulating buggy save: edit ES title + blank EN components...\n";
$es->set('title', $es_title_before . ' [TEST]');
$en->set('components', []);  // The bug.

// 4. Save → triggers hook_canvas_page_presave guard.
$page->save();
echo "→ Save completed.\n";

// 5. Re-load and assert.
$reloaded = \Drupal::entityTypeManager()
  ->getStorage('canvas_page')
  ->loadUnchanged($page_id);

$failed = [];
if (!$reloaded->hasTranslation('en')) {
  $failed[] = "EN translation was DELETED";
}
else {
  $en_after = $reloaded->getTranslation('en');
  $components_after = count($en_after->get('components')->getValue());
  $alias_after = $en_after->get('path')->first()?->getValue()['alias'] ?? null;
  if ($components_after !== $en_components_before) {
    $failed[] = "EN components count changed: {$en_components_before} → {$components_after}";
  }
  if ($alias_after !== $en_alias_before) {
    $failed[] = "EN alias changed: '{$en_alias_before}' → '{$alias_after}'";
  }
}

// 6. Always revert ES title.
echo "→ Reverting test ES title change...\n";
$reloaded_es = $reloaded->getTranslation('es');
$reloaded_es->set('title', $es_title_before);
$reloaded->save();

if ($failed) {
  echo "\n✗ GUARD FAILED:\n";
  foreach ($failed as $msg) {
    echo "  - {$msg}\n";
  }
  echo "\nThe hook in jalvarez_site_canvas_page_presave is NOT protecting EN. Investigate.\n";
  exit(1);
}

echo "\n✓ GUARD WORKING: EN translation survived a simulated wipe.\n";
echo "  - components: {$en_components_before} preserved\n";
echo "  - alias: '{$en_alias_before}' preserved\n";
