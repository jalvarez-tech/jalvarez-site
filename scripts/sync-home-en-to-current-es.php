<?php
/**
 * @file
 * Re-sync the EN translation of the home canvas_page to match the current
 * ES content. Targets the props the editor diverged on the ES side and
 * leaves alone the rest (most of the home is already in lockstep).
 *
 * Targeted updates:
 *   • banner-inicio.title_*  — ES dropped "honesta" from the headline; EN
 *     still had the old "I believe a website / fast / honest / and
 *     inclusive / is a form of respect" structure.
 *   • banner-inicio.sub      — EN had an extra sentence ("I am John
 *     Stevans Alvarez…") not present in ES; trimming.
 *
 * Idempotent. Safe to re-run.
 *
 * Run via:
 *   ddev exec ./web/vendor/bin/drush php:script scripts/sync-home-en-to-current-es.php
 *   gh workflow run seed-content.yml --field script=scripts/sync-home-en-to-current-es.php
 */

use Drupal\canvas\Entity\Page;

// Headline structure in EN that mirrors ES today:
//   ES:  Una web | rápida | , |  e inclusiva  | es una forma | de respeto.
//   EN:  A      | fast    | , |  inclusive   | web is a     | form of respect.
// Yields visually: "A fast, inclusive web is a form of respect."
$en_banner_updates = [
  'title_a'     => 'A',
  'title_accent' => 'fast',
  'title_punc'  => ',',
  'title_stroke' => ' inclusive ',
  'title_b'     => 'web is a',
  'title_muted' => 'form of respect.',
  // Trim sub to mirror ES (remove the bonus "I am John…" sentence).
  'sub' => "I build digital experiences focused on performance, accessibility, and clarity. Sites that don't waste time, don't exclude users, and don't over-promise.",
];

// Resolve the home by ES alias.
$home_path = \Drupal::service('path_alias.manager')->getPathByAlias('/inicio');
if (!preg_match('#^/page/(\d+)$#', $home_path, $m)) {
  fwrite(STDERR, "✗ Could not resolve '/inicio' to a canvas_page.\n");
  exit(1);
}
$page = Page::load((int) $m[1]);
if (!$page->hasTranslation('en')) {
  fwrite(STDERR, "✗ EN translation missing on canvas_page {$page->id()}.\n");
  exit(1);
}
$en = $page->getTranslation('en');

$tree = $en->get('components')->getValue();
$touched = FALSE;
foreach ($tree as $i => $row) {
  if ($row['component_id'] !== 'sdc.byte.banner-inicio') continue;
  $inputs = json_decode($row['inputs'], TRUE) ?? [];
  echo "→ Updating EN banner-inicio (uuid={$row['uuid']})\n";
  foreach ($en_banner_updates as $prop => $new_value) {
    $old = isset($inputs[$prop])
      ? (is_array($inputs[$prop]) ? ($inputs[$prop]['value'] ?? '<wrapped>') : $inputs[$prop])
      : '<unset>';
    if (is_array($inputs[$prop] ?? NULL) && array_key_exists('value', $inputs[$prop])) {
      $inputs[$prop]['value'] = $new_value;
    }
    else {
      $inputs[$prop] = $new_value;
    }
    $old_short = is_string($old) && strlen($old) > 70 ? substr($old, 0, 67) . '…' : $old;
    $new_short = is_string($new_value) && strlen($new_value) > 70 ? substr($new_value, 0, 67) . '…' : $new_value;
    echo "  · {$prop}: '" . $old_short . "' → '" . $new_short . "'\n";
  }
  $tree[$i]['inputs'] = json_encode($inputs, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
  $touched = TRUE;
  break;
}

if ($touched) {
  $en->set('components', $tree);
  $en->save();
  drupal_flush_all_caches();
  echo "\n✓ EN banner-inicio re-synced. Visit /en to verify.\n";
}
else {
  echo "✗ No banner-inicio component found in EN tree.\n";
}
