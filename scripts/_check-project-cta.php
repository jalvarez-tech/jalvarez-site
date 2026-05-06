<?php
/**
 * @file
 * Quick: list every project node and whether field_cta_heading is set.
 * Used to know if the case-detail CTA renders or not in prod.
 */

$ids = \Drupal::entityQuery('node')->condition('type', 'project')->accessCheck(FALSE)->execute();
foreach (\Drupal\node\Entity\Node::loadMultiple($ids) as $n) {
  foreach ($n->getTranslationLanguages() as $lc => $_) {
    $t = $n->getTranslation($lc);
    $heading = $t->hasField('field_cta_heading') && !$t->get('field_cta_heading')->isEmpty()
      ? $t->get('field_cta_heading')->value
      : '(empty)';
    echo "nid={$n->id()} {$lc} '{$t->label()}' — field_cta_heading: {$heading}\n";
  }
}
