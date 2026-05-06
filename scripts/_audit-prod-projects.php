<?php
use Drupal\node\Entity\Node;
foreach (\Drupal::entityQuery('node')->condition('type','project')->accessCheck(FALSE)->execute() as $nid) {
  $n = Node::load($nid);
  echo "=== nid={$nid} '{$n->label()}' ===\n";
  foreach ($n->getTranslationLanguages() as $lc => $_) {
    $t = $n->getTranslation($lc);
    foreach (['field_approach_steps','field_results_metrics','field_testimonial_embed'] as $f) {
      $vals = $t->get($f)->getValue();
      $ids  = implode(',', array_column($vals, 'target_id'));
      echo "  {$lc}.{$f}: " . count($vals) . " refs [{$ids}]\n";
    }
  }
  echo "\n";
}
