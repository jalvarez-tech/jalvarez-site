<?php
/**
 * @file
 * Update the marquee component on the Inicio canvas_page (ES + EN) with
 * ATS-friendly keywords pulled from the CV.
 *
 * Why this script: the marquee items are stored as flat string props
 * (item_1..item_9) per translation. Canvas 1.3.x's visual editor only
 * edits the default translation, so we update both ES and EN explicitly
 * here. Idempotent — re-running just re-applies the same values.
 *
 * Keyword priority (ATS-aware, ordered by recruiter relevance):
 *   1. Drupal 7-11        — full version range (vs only "Drupal 11")
 *   2. WCAG 2.2           — compliance keyword
 *   3. Headless Drupal    — modern architecture pattern
 *   4. React              — frontend framework
 *   5. JSON:API           — explicit API spec
 *   6. GraphQL            — alt API skill
 *   7. Twig               — Drupal frontend layer
 *   8. Symfony            — backend framework underpinning Drupal
 *   9. Core Web Vitals    — performance keyword
 *
 * Run via:
 *   ddev exec ./web/vendor/bin/drush php:script scripts/update-marquee-cv-keywords.php
 *   gh workflow run seed-content.yml --field script=scripts/update-marquee-cv-keywords.php
 */

use Drupal\canvas\Entity\Page;

$keywords = [
  'item_1' => 'Drupal 7-11',
  'item_2' => 'WCAG 2.2',
  'item_3' => 'Headless Drupal',
  'item_4' => 'React',
  'item_5' => 'JSON:API',
  'item_6' => 'GraphQL',
  'item_7' => 'Twig',
  'item_8' => 'Symfony',
  'item_9' => 'Core Web Vitals',
];

// Resolve the home canvas_page by ES alias (portable across envs).
$home_path = \Drupal::service('path_alias.manager')->getPathByAlias('/inicio');
if (!preg_match('#^/page/(\d+)$#', $home_path, $m)) {
  fwrite(STDERR, "✗ Could not resolve '/inicio' to a canvas_page.\n");
  exit(1);
}
$page = Page::load((int) $m[1]);
echo "→ Target: canvas_page id={$page->id()} title='{$page->label()}'\n";

// Apply to every translation that exists.
foreach ($page->getTranslationLanguages() as $langcode => $_) {
  $trans = $page->getTranslation($langcode);
  $tree = $trans->get('components')->getValue();
  $touched = FALSE;
  foreach ($tree as $i => $row) {
    if ($row['component_id'] !== 'sdc.byte.marquee') continue;
    $inputs = json_decode($row['inputs'], TRUE) ?? [];
    foreach ($keywords as $prop => $value) {
      // Inputs may be flat (visual editor format) or wrapped (script format).
      // Handle both — sniff the existing shape and preserve it.
      if (isset($inputs[$prop]) && is_array($inputs[$prop]) && array_key_exists('value', $inputs[$prop])) {
        $inputs[$prop]['value'] = $value;
      }
      else {
        $inputs[$prop] = $value;
      }
    }
    $tree[$i]['inputs'] = json_encode($inputs, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    $touched = TRUE;
    echo "  · {$langcode}: marquee uuid={$row['uuid']} updated\n";
    break;
  }
  if ($touched) {
    $trans->set('components', $tree);
    $trans->save();
  }
  else {
    echo "  ⚠ {$langcode}: no marquee component found in tree\n";
  }
}

drupal_flush_all_caches();
echo "\n✓ Marquee items updated for all translations:\n";
foreach ($keywords as $prop => $value) {
  echo "    {$prop} = {$value}\n";
}
