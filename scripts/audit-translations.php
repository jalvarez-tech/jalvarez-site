<?php
/**
 * @file
 * Audit translation state across all entity types we manage.
 */

use Drupal\node\Entity\Node;
use Drupal\canvas\Entity\Page;
use Drupal\taxonomy\Entity\Term;

echo "── canvas_page entities ──\n";
foreach (Page::loadMultiple() as $p) {
  $langs = implode(',', array_keys($p->getTranslationLanguages()));
  $aliases = [];
  foreach (\Drupal::entityTypeManager()->getStorage('path_alias')
    ->loadByProperties(['path' => '/page/' . $p->id()]) as $a) {
    $aliases[] = "[{$a->language()->getId()}] {$a->getAlias()}";
  }
  printf("  · id=%d title=%-22s langs=%s · aliases: %s\n", $p->id(), '"'.$p->label().'"', $langs, implode(' ', $aliases));
}

echo "\n── node.project ──\n";
$nids = \Drupal::entityQuery('node')->condition('type', 'project')->accessCheck(FALSE)->execute();
foreach (Node::loadMultiple($nids) as $n) {
  $langs = implode(',', array_keys($n->getTranslationLanguages()));
  printf("  · nid=%-3d title=%-25s langs=%s\n", $n->id(), '"'.$n->label().'"', $langs);
}

echo "\n── node.note ──\n";
$nids = \Drupal::entityQuery('node')->condition('type', 'note')->accessCheck(FALSE)->execute();
foreach (Node::loadMultiple($nids) as $n) {
  $langs = implode(',', array_keys($n->getTranslationLanguages()));
  printf("  · nid=%-3d title=%-60s langs=%s\n", $n->id(), '"'.substr($n->label(), 0, 55).'"', $langs);
}

echo "\n── Taxonomy terms ──\n";
$vocabs = \Drupal::entityTypeManager()->getStorage('taxonomy_vocabulary')->loadMultiple();
foreach ($vocabs as $vid => $vocab) {
  echo "  vocab '{$vid}':\n";
  $tids = \Drupal::entityQuery('taxonomy_term')->condition('vid', $vid)->accessCheck(FALSE)->execute();
  foreach (Term::loadMultiple($tids) as $t) {
    $langs = implode(',', array_keys($t->getTranslationLanguages()));
    printf("    · tid=%-3d %-30s langs=%s\n", $t->id(), '"'.$t->label().'"', $langs);
  }
}

echo "\n── Webforms ──\n";
$webforms = \Drupal::entityTypeManager()->getStorage('webform')->loadMultiple();
foreach ($webforms as $w) {
  echo "  · {$w->id()}: '{$w->label()}'\n";
  // Check if config_translation has entries for this webform.
  $config_name = 'webform.webform.' . $w->id();
  $lang_manager = \Drupal::languageManager();
  if ($lang_manager->isMultilingual()) {
    foreach ($lang_manager->getLanguages() as $lang_code => $lang) {
      if ($lang_code === $w->language()->getId()) continue;
      $cfg = $lang_manager->getLanguageConfigOverride($lang_code, $config_name);
      $has = !empty($cfg->get());
      echo "    · translation [{$lang_code}]: " . ($has ? 'YES' : 'NO') . "\n";
    }
  }
}
