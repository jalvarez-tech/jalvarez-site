<?php
$nids = \Drupal::entityQuery('node')->condition('type', 'page')->accessCheck(FALSE)->execute();
foreach ($nids as $nid) {
  $n = \Drupal\node\Entity\Node::load($nid);
  echo "nid={$nid} title=" . $n->label() . " langs=" . implode(",", array_keys($n->getTranslationLanguages())) . "\n";
}

echo "\n── path_alias entities for page nodes ──\n";
$aliases = \Drupal::entityTypeManager()->getStorage('path_alias')->loadMultiple();
foreach ($aliases as $a) {
  if (str_contains($a->getPath(), '/node/13')) {
    echo "  · path_alias id={$a->id()} path={$a->getPath()} alias={$a->getAlias()} langcode={$a->language()->getId()}\n";
  }
}

echo "\n── system.site front page ──\n";
echo "  page.front = " . \Drupal::config('system.site')->get('page.front') . "\n";
