<?php
/**
 * @file
 * Update the LinkedIn URL on the canal-directo block of /contacto. The
 * footer's LinkedIn link is hardcoded in FooterBlock.php so a code change
 * covers it; this script handles the entity-stored copy on the Contacto
 * canvas_page (ES + EN).
 *
 * Idempotent. Safe to re-run.
 */

use Drupal\canvas\Entity\Page;

$new_url   = 'https://www.linkedin.com/in/jalvarez-tech/';
$new_value = 'in/jalvarez-tech ↗';

$path = \Drupal::service('path_alias.manager')->getPathByAlias('/contacto');
if (!preg_match('#^/page/(\d+)$#', $path, $m)) {
  fwrite(STDERR, "✗ Could not resolve '/contacto'.\n");
  exit(1);
}
$page = Page::load((int) $m[1]);

foreach ($page->getTranslationLanguages() as $langcode => $_) {
  $trans = $page->getTranslation($langcode);
  $tree = $trans->get('components')->getValue();
  $touched = FALSE;
  foreach ($tree as $i => $row) {
    if ($row['component_id'] !== 'sdc.byte.canal-directo') continue;
    $inputs = json_decode($row['inputs'], TRUE) ?? [];
    $set = function (string $key, $value) use (&$inputs) {
      if (is_array($inputs[$key] ?? NULL) && array_key_exists('value', $inputs[$key])) {
        $inputs[$key]['value'] = $value;
      }
      else {
        $inputs[$key] = $value;
      }
    };
    // c3 = LinkedIn slot.
    $set('c3_name', 'LinkedIn');
    $set('c3_value', $new_value);
    $set('c3_href',  $new_url);
    $tree[$i]['inputs'] = json_encode($inputs, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    $touched = TRUE;
    break;
  }
  if ($touched) {
    $trans->set('components', $tree);
    $trans->save();
    echo "✓ /contacto ({$langcode}): canal-directo c3 → '{$new_value}' → {$new_url}\n";
  }
}

drupal_flush_all_caches();
echo "\n✓ LinkedIn URL updated everywhere.\n";
